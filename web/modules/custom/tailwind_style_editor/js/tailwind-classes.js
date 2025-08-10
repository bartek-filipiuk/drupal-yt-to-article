/**
 * Tailwind Classes Database
 */
(function (Drupal) {
  'use strict';

  /**
   * Tailwind classes organized by category
   */
  const tailwindClasses = {
    'Layout': [
      'block', 'inline-block', 'inline', 'flex', 'inline-flex', 'grid', 'inline-grid', 'hidden',
      'container', 'mx-auto'
    ],
    
    'Flexbox': [
      'flex-row', 'flex-row-reverse', 'flex-col', 'flex-col-reverse',
      'flex-wrap', 'flex-nowrap', 'flex-wrap-reverse',
      'items-start', 'items-center', 'items-end', 'items-baseline', 'items-stretch',
      'justify-start', 'justify-center', 'justify-end', 'justify-between', 'justify-around', 'justify-evenly',
      'gap-0', 'gap-1', 'gap-2', 'gap-3', 'gap-4', 'gap-5', 'gap-6', 'gap-8', 'gap-10', 'gap-12'
    ],
    
    'Grid': [
      'grid-cols-1', 'grid-cols-2', 'grid-cols-3', 'grid-cols-4', 'grid-cols-5', 'grid-cols-6',
      'grid-cols-12', 'col-span-1', 'col-span-2', 'col-span-3', 'col-span-4', 'col-span-5', 'col-span-6',
      'grid-rows-1', 'grid-rows-2', 'grid-rows-3', 'grid-rows-4', 'grid-rows-5', 'grid-rows-6'
    ],
    
    'Spacing - Padding': [
      'p-0', 'p-1', 'p-2', 'p-3', 'p-4', 'p-5', 'p-6', 'p-8', 'p-10', 'p-12', 'p-16', 'p-20',
      'px-0', 'px-1', 'px-2', 'px-3', 'px-4', 'px-5', 'px-6', 'px-8', 'px-10', 'px-12',
      'py-0', 'py-1', 'py-2', 'py-3', 'py-4', 'py-5', 'py-6', 'py-8', 'py-10', 'py-12',
      'pt-0', 'pt-1', 'pt-2', 'pt-3', 'pt-4', 'pt-5', 'pt-6', 'pt-8',
      'pr-0', 'pr-1', 'pr-2', 'pr-3', 'pr-4', 'pr-5', 'pr-6', 'pr-8',
      'pb-0', 'pb-1', 'pb-2', 'pb-3', 'pb-4', 'pb-5', 'pb-6', 'pb-8',
      'pl-0', 'pl-1', 'pl-2', 'pl-3', 'pl-4', 'pl-5', 'pl-6', 'pl-8'
    ],
    
    'Spacing - Margin': [
      'm-0', 'm-1', 'm-2', 'm-3', 'm-4', 'm-5', 'm-6', 'm-8', 'm-10', 'm-12', 'm-auto',
      'mx-0', 'mx-1', 'mx-2', 'mx-3', 'mx-4', 'mx-5', 'mx-6', 'mx-8', 'mx-auto',
      'my-0', 'my-1', 'my-2', 'my-3', 'my-4', 'my-5', 'my-6', 'my-8',
      'mt-0', 'mt-1', 'mt-2', 'mt-3', 'mt-4', 'mt-5', 'mt-6', 'mt-8',
      'mr-0', 'mr-1', 'mr-2', 'mr-3', 'mr-4', 'mr-5', 'mr-6', 'mr-8',
      'mb-0', 'mb-1', 'mb-2', 'mb-3', 'mb-4', 'mb-5', 'mb-6', 'mb-8',
      'ml-0', 'ml-1', 'ml-2', 'ml-3', 'ml-4', 'ml-5', 'ml-6', 'ml-8'
    ],
    
    'Width': [
      'w-0', 'w-1', 'w-2', 'w-3', 'w-4', 'w-5', 'w-6', 'w-8', 'w-10', 'w-12', 'w-16', 'w-20', 'w-24',
      'w-32', 'w-40', 'w-48', 'w-56', 'w-64', 'w-auto', 'w-1/2', 'w-1/3', 'w-2/3', 'w-1/4', 'w-3/4',
      'w-full', 'w-screen', 'max-w-xs', 'max-w-sm', 'max-w-md', 'max-w-lg', 'max-w-xl', 'max-w-2xl',
      'max-w-3xl', 'max-w-4xl', 'max-w-5xl', 'max-w-6xl', 'max-w-full'
    ],
    
    'Height': [
      'h-0', 'h-1', 'h-2', 'h-3', 'h-4', 'h-5', 'h-6', 'h-8', 'h-10', 'h-12', 'h-16', 'h-20', 'h-24',
      'h-32', 'h-40', 'h-48', 'h-56', 'h-64', 'h-auto', 'h-full', 'h-screen'
    ],
    
    'Typography - Size': [
      'text-xs', 'text-sm', 'text-base', 'text-lg', 'text-xl', 'text-2xl', 'text-3xl', 'text-4xl',
      'text-5xl', 'text-6xl', 'text-7xl', 'text-8xl', 'text-9xl'
    ],
    
    'Typography - Style': [
      'font-thin', 'font-extralight', 'font-light', 'font-normal', 'font-medium', 'font-semibold',
      'font-bold', 'font-extrabold', 'font-black', 'italic', 'not-italic', 'uppercase', 'lowercase',
      'capitalize', 'normal-case', 'underline', 'line-through', 'no-underline'
    ],
    
    'Typography - Alignment': [
      'text-left', 'text-center', 'text-right', 'text-justify'
    ],
    
    'Colors - Text': [
      'text-white', 'text-black', 'text-gray-50', 'text-gray-100', 'text-gray-200', 'text-gray-300',
      'text-gray-400', 'text-gray-500', 'text-gray-600', 'text-gray-700', 'text-gray-800', 'text-gray-900',
      'text-red-500', 'text-green-500', 'text-blue-500', 'text-yellow-500', 'text-purple-500',
      'text-pink-500', 'text-indigo-500', 'text-drupal-primary', 'text-drupal-secondary',
      'text-drupal-text', 'text-drupal-muted'
    ],
    
    'Colors - Background': [
      'bg-white', 'bg-black', 'bg-transparent', 'bg-gray-50', 'bg-gray-100', 'bg-gray-200',
      'bg-gray-300', 'bg-gray-400', 'bg-gray-500', 'bg-gray-600', 'bg-gray-700', 'bg-gray-800',
      'bg-gray-900', 'bg-red-500', 'bg-green-500', 'bg-blue-500', 'bg-yellow-500', 'bg-purple-500',
      'bg-pink-500', 'bg-indigo-500', 'bg-drupal-primary', 'bg-drupal-secondary',
      'bg-drupal-surface', 'bg-drupal-border'
    ],
    
    'Borders': [
      'border', 'border-0', 'border-2', 'border-4', 'border-8',
      'border-t', 'border-r', 'border-b', 'border-l',
      'border-gray-200', 'border-gray-300', 'border-gray-400', 'border-gray-500',
      'border-drupal-border', 'border-drupal-primary',
      'border-solid', 'border-dashed', 'border-dotted', 'border-none'
    ],
    
    'Border Radius': [
      'rounded-none', 'rounded-sm', 'rounded', 'rounded-md', 'rounded-lg', 'rounded-xl',
      'rounded-2xl', 'rounded-3xl', 'rounded-full'
    ],
    
    'Effects - Shadow': [
      'shadow-sm', 'shadow', 'shadow-md', 'shadow-lg', 'shadow-xl', 'shadow-2xl', 'shadow-none'
    ],
    
    'Effects - Opacity': [
      'opacity-0', 'opacity-25', 'opacity-50', 'opacity-75', 'opacity-100'
    ],
    
    'Transitions': [
      'transition-none', 'transition-all', 'transition', 'transition-colors', 'transition-opacity',
      'transition-shadow', 'transition-transform', 'duration-75', 'duration-100', 'duration-150',
      'duration-200', 'duration-300', 'duration-500', 'duration-700', 'duration-1000'
    ],
    
    'Transforms': [
      'scale-0', 'scale-50', 'scale-75', 'scale-90', 'scale-95', 'scale-100', 'scale-105',
      'scale-110', 'scale-125', 'scale-150', 'rotate-0', 'rotate-45', 'rotate-90', 'rotate-180',
      '-rotate-45', '-rotate-90', '-rotate-180', 'translate-x-0', 'translate-y-0'
    ],
    
    'Position': [
      'static', 'fixed', 'absolute', 'relative', 'sticky',
      'top-0', 'right-0', 'bottom-0', 'left-0', 'inset-0', 'z-0', 'z-10', 'z-20', 'z-30', 'z-40', 'z-50'
    ],
    
    'Overflow': [
      'overflow-auto', 'overflow-hidden', 'overflow-visible', 'overflow-scroll',
      'overflow-x-auto', 'overflow-y-auto', 'overflow-x-hidden', 'overflow-y-hidden'
    ],
    
    'Responsive': [
      'sm:', 'md:', 'lg:', 'xl:', '2xl:'
    ],
    
    'Pseudo-classes': [
      'hover:', 'focus:', 'active:', 'visited:', 'disabled:', 'group-hover:'
    ]
  };

  /**
   * Export classes for use in other scripts
   */
  Drupal.TailwindClasses = {
    /**
     * Get all categories
     */
    getCategories: function() {
      return tailwindClasses;
    },
    
    /**
     * Get classes for a specific category
     */
    getCategory: function(category) {
      return tailwindClasses[category] || [];
    },
    
    /**
     * Search classes
     */
    searchClasses: function(query) {
      const results = [];
      const lowerQuery = query.toLowerCase();
      
      Object.entries(tailwindClasses).forEach(([category, classes]) => {
        classes.forEach(className => {
          if (className.toLowerCase().includes(lowerQuery)) {
            results.push({
              category: category,
              class: className
            });
          }
        });
      });
      
      return results;
    },
    
    /**
     * Check if a class is a valid Tailwind class
     */
    isValidClass: function(className) {
      return Object.values(tailwindClasses).some(classes => classes.includes(className));
    }
  };

})(Drupal);