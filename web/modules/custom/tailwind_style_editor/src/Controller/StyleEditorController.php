<?php

namespace Drupal\tailwind_style_editor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for Tailwind Style Editor.
 */
class StyleEditorController extends ControllerBase {

  /**
   * Save styles.
   */
  public function saveStyles(Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    
    if (!$data) {
      return new JsonResponse(['error' => 'Invalid data'], 400);
    }
    
    // Store styles in config or custom table
    $config = \Drupal::service('config.factory')->getEditable('tailwind_style_editor.styles');
    
    $selector = $data['selector'] ?? '';
    $classes = $data['classes'] ?? [];
    $path = $data['path'] ?? '';
    
    // Get existing styles
    $styles = $config->get('styles') ?? [];
    
    // Add/update style
    $styles[$path][$selector] = $classes;
    
    $config->set('styles', $styles)->save();
    
    return new JsonResponse(['success' => TRUE]);
  }

  /**
   * Load styles.
   */
  public function loadStyles(Request $request) {
    $path = $request->query->get('path', '');
    
    $config = \Drupal::config('tailwind_style_editor.styles');
    $styles = $config->get('styles') ?? [];
    
    $pageStyles = $styles[$path] ?? [];
    
    return new JsonResponse($pageStyles);
  }

  /**
   * Export styles.
   */
  public function exportStyles() {
    $config = \Drupal::config('tailwind_style_editor.styles');
    $styles = $config->get('styles') ?? [];
    
    // Generate CSS from saved styles
    $css = "/* Tailwind Style Editor - Exported Styles */\n\n";
    
    foreach ($styles as $path => $pageStyles) {
      $css .= "/* Page: $path */\n";
      foreach ($pageStyles as $selector => $classes) {
        if (!empty($classes)) {
          $css .= "$selector {\n";
          $css .= "  @apply " . implode(' ', $classes) . ";\n";
          $css .= "}\n\n";
        }
      }
    }
    
    $response = new Response($css);
    $response->headers->set('Content-Type', 'text/css');
    $response->headers->set('Content-Disposition', 'attachment; filename="tailwind-styles.css"');
    
    return $response;
  }
}