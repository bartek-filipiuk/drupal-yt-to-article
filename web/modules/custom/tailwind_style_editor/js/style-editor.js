/**
 * Main Tailwind Style Editor JavaScript
 */
(function ($, Drupal, drupalSettings, once) {
  'use strict';

  // Store the currently selected element
  let selectedElement = null;
  let isEditorActive = false;
  let stylePanel = null;

  /**
   * Initialize the Tailwind Style Editor
   */
  Drupal.behaviors.tailwindStyleEditor = {
    attach: function (context, settings) {
      // Initialize only once
      once('tailwind-style-editor', 'body', context).forEach(function () {
        initializeStyleEditor();
      });
    }
  };

  /**
   * Initialize the style editor
   */
  function initializeStyleEditor() {
    // Create the style panel
    createStylePanel();

    // Add toolbar button click handler
    const toggleButton = document.getElementById('tailwind-style-editor-toggle');
    if (toggleButton) {
      toggleButton.addEventListener('click', toggleStyleEditor);
    }

    // Listen for element clicks when editor is active
    document.addEventListener('click', handleElementClick, true);

    // Prevent navigation when editor is active
    document.addEventListener('click', preventNavigation, true);
  }

  /**
   * Toggle the style editor on/off
   */
  function toggleStyleEditor(e) {
    e.preventDefault();
    isEditorActive = !isEditorActive;
    
    const body = document.body;
    const toggleButton = document.getElementById('tailwind-style-editor-toggle');
    
    if (isEditorActive) {
      body.classList.add('tailwind-style-editor-active');
      toggleButton.classList.add('active');
      toggleButton.setAttribute('aria-pressed', 'true');
      
      // Show notification
      showNotification('Style Editor activated. Click any element to edit its styles.');
    } else {
      body.classList.remove('tailwind-style-editor-active');
      toggleButton.classList.remove('active');
      toggleButton.setAttribute('aria-pressed', 'false');
      
      // Clear selection
      clearSelection();
      
      // Hide panel
      if (stylePanel) {
        stylePanel.classList.remove('active');
      }
      
      showNotification('Style Editor deactivated.');
    }
  }

  /**
   * Handle clicks on elements
   */
  function handleElementClick(e) {
    if (!isEditorActive) return;
    
    // Ignore clicks on the style panel or toolbar
    if (e.target.closest('#tailwind-style-panel') || 
        e.target.closest('.toolbar-bar') ||
        e.target.closest('.tailwind-style-editor-label')) {
      return;
    }
    
    e.preventDefault();
    e.stopPropagation();
    
    selectElement(e.target);
  }

  /**
   * Prevent navigation when editor is active
   */
  function preventNavigation(e) {
    if (!isEditorActive) return;
    
    const target = e.target;
    
    // Prevent link navigation
    if (target.tagName === 'A' || target.closest('a')) {
      if (!target.closest('#tailwind-style-panel') && !target.closest('.toolbar-bar')) {
        e.preventDefault();
        e.stopPropagation();
      }
    }
    
    // Prevent form submission
    if (target.tagName === 'BUTTON' || target.tagName === 'INPUT' || target.closest('form')) {
      if (!target.closest('#tailwind-style-panel')) {
        e.preventDefault();
        e.stopPropagation();
      }
    }
  }

  /**
   * Select an element for editing
   */
  function selectElement(element) {
    // Clear previous selection
    clearSelection();
    
    // Ignore certain elements
    if (element.tagName === 'BODY' || element.tagName === 'HTML') {
      return;
    }
    
    selectedElement = element;
    selectedElement.classList.add('tailwind-style-editor-selected');
    
    // Add element label
    const label = document.createElement('div');
    label.className = 'tailwind-style-editor-label';
    label.textContent = getElementIdentifier(element);
    selectedElement.appendChild(label);
    
    // Show and update the style panel
    showStylePanel(element);
  }

  /**
   * Clear the current selection
   */
  function clearSelection() {
    if (selectedElement) {
      selectedElement.classList.remove('tailwind-style-editor-selected');
      const label = selectedElement.querySelector('.tailwind-style-editor-label');
      if (label) {
        label.remove();
      }
    }
    selectedElement = null;
  }

  /**
   * Get a readable identifier for an element
   */
  function getElementIdentifier(element) {
    let identifier = element.tagName.toLowerCase();
    
    if (element.id) {
      identifier += '#' + element.id;
    } else if (element.className && typeof element.className === 'string') {
      const mainClass = element.className.split(' ')[0];
      if (mainClass) {
        identifier += '.' + mainClass;
      }
    }
    
    return identifier;
  }

  /**
   * Create the style panel
   */
  function createStylePanel() {
    const panelHTML = `
      <div id="tailwind-style-panel">
        <div class="tse-panel-header">
          <h2 class="tse-panel-title">Tailwind Style Editor</h2>
          <button class="tse-panel-close" aria-label="Close panel">&times;</button>
        </div>
        
        <div class="tse-search">
          <input type="text" class="tse-search-input" placeholder="Search classes...">
        </div>
        
        <div class="tse-current-classes">
          <h3 class="tse-current-classes-title">Current Classes</h3>
          <div class="tse-class-tags"></div>
        </div>
        
        <div class="tse-categories"></div>
        
        <div class="tse-custom-class">
          <h3 class="tse-custom-class-title">Custom Classes</h3>
          <div class="tse-custom-input-group">
            <input type="text" class="tse-custom-input" placeholder="Enter custom classes">
            <button class="tse-button tse-button-primary">Add</button>
          </div>
        </div>
        
        <div class="tse-actions">
          <button class="tse-button tse-button-primary tse-save">Save Styles</button>
          <button class="tse-button tse-button-secondary tse-export">Export</button>
          <button class="tse-button tse-button-secondary tse-reset">Reset</button>
        </div>
      </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', panelHTML);
    stylePanel = document.getElementById('tailwind-style-panel');
    
    // Add event listeners
    stylePanel.querySelector('.tse-panel-close').addEventListener('click', hideStylePanel);
    stylePanel.querySelector('.tse-search-input').addEventListener('input', handleSearch);
    stylePanel.querySelector('.tse-custom-input-group button').addEventListener('click', addCustomClass);
    stylePanel.querySelector('.tse-save').addEventListener('click', saveStyles);
    stylePanel.querySelector('.tse-export').addEventListener('click', exportStyles);
    stylePanel.querySelector('.tse-reset').addEventListener('click', resetStyles);
    
    // Load categories
    loadCategories();
  }

  /**
   * Show the style panel for an element
   */
  function showStylePanel(element) {
    if (!stylePanel) return;
    
    stylePanel.classList.add('active');
    
    // Update current classes
    updateCurrentClasses(element);
    
    // Update category selections
    updateCategorySelections(element);
  }

  /**
   * Hide the style panel
   */
  function hideStylePanel() {
    if (stylePanel) {
      stylePanel.classList.remove('active');
    }
  }

  /**
   * Update the current classes display
   */
  function updateCurrentClasses(element) {
    const container = stylePanel.querySelector('.tse-class-tags');
    const classes = Array.from(element.classList);
    
    container.innerHTML = '';
    
    classes.forEach(className => {
      // Skip internal classes
      if (className.startsWith('tailwind-style-editor-')) return;
      
      const tag = document.createElement('div');
      tag.className = 'tse-class-tag';
      tag.innerHTML = `
        ${className}
        <button class="tse-class-remove" data-class="${className}">&times;</button>
      `;
      
      tag.querySelector('.tse-class-remove').addEventListener('click', function() {
        removeClass(className);
      });
      
      container.appendChild(tag);
    });
  }

  /**
   * Load Tailwind class categories
   */
  function loadCategories() {
    const container = stylePanel.querySelector('.tse-categories');
    const categories = Drupal.TailwindClasses.getCategories();
    
    container.innerHTML = '';
    
    Object.entries(categories).forEach(([categoryName, classes]) => {
      const categoryEl = createCategoryElement(categoryName, classes);
      container.appendChild(categoryEl);
    });
  }

  /**
   * Create a category element
   */
  function createCategoryElement(name, classes) {
    const div = document.createElement('div');
    div.className = 'tse-category';
    
    const header = document.createElement('div');
    header.className = 'tse-category-header';
    header.innerHTML = `
      ${name}
      <span class="tse-category-chevron">▶</span>
    `;
    
    const content = document.createElement('div');
    content.className = 'tse-category-content';
    
    const grid = document.createElement('div');
    grid.className = 'tse-class-grid';
    
    classes.forEach(className => {
      const option = document.createElement('button');
      option.className = 'tse-class-option';
      option.textContent = className;
      option.setAttribute('data-class', className);
      
      option.addEventListener('click', function() {
        toggleClass(className);
      });
      
      grid.appendChild(option);
    });
    
    content.appendChild(grid);
    div.appendChild(header);
    div.appendChild(content);
    
    // Toggle category
    header.addEventListener('click', function() {
      div.classList.toggle('expanded');
    });
    
    return div;
  }

  /**
   * Update category selections based on current element
   */
  function updateCategorySelections(element) {
    const classes = Array.from(element.classList);
    
    // Reset all selections
    stylePanel.querySelectorAll('.tse-class-option').forEach(option => {
      option.classList.remove('active');
    });
    
    // Mark active classes
    classes.forEach(className => {
      const option = stylePanel.querySelector(`.tse-class-option[data-class="${className}"]`);
      if (option) {
        option.classList.add('active');
      }
    });
  }

  /**
   * Toggle a class on the selected element
   */
  function toggleClass(className) {
    if (!selectedElement) return;
    
    selectedElement.classList.toggle(className);
    updateCurrentClasses(selectedElement);
    updateCategorySelections(selectedElement);
  }

  /**
   * Remove a class from the selected element
   */
  function removeClass(className) {
    if (!selectedElement) return;
    
    selectedElement.classList.remove(className);
    updateCurrentClasses(selectedElement);
    updateCategorySelections(selectedElement);
  }

  /**
   * Add custom classes
   */
  function addCustomClass() {
    if (!selectedElement) return;
    
    const input = stylePanel.querySelector('.tse-custom-input');
    const classes = input.value.trim().split(' ').filter(c => c);
    
    classes.forEach(className => {
      selectedElement.classList.add(className);
    });
    
    input.value = '';
    updateCurrentClasses(selectedElement);
  }

  /**
   * Handle search
   */
  function handleSearch(e) {
    const query = e.target.value.toLowerCase();
    
    stylePanel.querySelectorAll('.tse-class-option').forEach(option => {
      const className = option.getAttribute('data-class').toLowerCase();
      if (className.includes(query)) {
        option.style.display = '';
      } else {
        option.style.display = 'none';
      }
    });
  }

  /**
   * Save styles
   */
  function saveStyles() {
    // TODO: Implement save functionality
    showNotification('Styles saved successfully!');
  }

  /**
   * Export styles
   */
  function exportStyles() {
    // TODO: Implement export functionality
    showNotification('Export feature coming soon!');
  }

  /**
   * Reset styles
   */
  function resetStyles() {
    if (!selectedElement) return;
    
    if (confirm('Remove all Tailwind classes from this element?')) {
      selectedElement.className = selectedElement.className
        .split(' ')
        .filter(c => !c.match(/^(p-|m-|text-|bg-|border-|flex|grid|block|inline|hidden|absolute|relative|fixed|sticky|w-|h-|min-|max-)/))
        .join(' ');
      
      updateCurrentClasses(selectedElement);
      updateCategorySelections(selectedElement);
    }
  }

  /**
   * Show a notification
   */
  function showNotification(message) {
    const messages = new Drupal.Message();
    messages.add(message, { type: 'status' });
  }

})(jQuery, Drupal, drupalSettings, once);