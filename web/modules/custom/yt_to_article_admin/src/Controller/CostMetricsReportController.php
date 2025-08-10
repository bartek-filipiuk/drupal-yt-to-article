<?php

declare(strict_types=1);

namespace Drupal\yt_to_article_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for cost metrics reports.
 */
class CostMetricsReportController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a CostMetricsReportController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Display the cost metrics report.
   *
   * @return array
   *   The render array.
   */
  public function report(): array {
    // Query cost metrics nodes
    $node_storage = $this->entityTypeManager->getStorage('node');
    
    // Get all cost metrics nodes
    $query = $node_storage->getQuery()
      ->condition('type', 'article_cost_metrics')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->accessCheck(TRUE);

    $nids = $query->execute();
    $nodes = $node_storage->loadMultiple($nids);

    // Calculate summary statistics
    $summary = $this->calculateSummary($nodes);

    // Get recent articles (last 20)
    $recent_articles = [];
    $count = 0;
    foreach ($nodes as $node) {
      if ($count >= 20) break;
      
      $recent_articles[] = [
        'title' => $node->getTitle(),
        'request_id' => $node->get('field_request_id')->value,
        'total_cost' => $node->get('field_total_cost_usd')->value ?? 0,
        'llm_cost' => $node->get('field_llm_cost_usd')->value ?? 0,
        'input_tokens' => $node->get('field_input_tokens')->value ?? 0,
        'output_tokens' => $node->get('field_output_tokens')->value ?? 0,
        'created' => $node->getCreatedTime(),
        'node_id' => $node->id(),
      ];
      $count++;
    }

    // Prepare daily costs for chart (last 30 days)
    $daily_costs = $this->calculateDailyCosts($nodes, 30);

    return [
      '#theme' => 'yt_article_cost_report',
      '#summary' => $summary,
      '#recent_articles' => $recent_articles,
      '#chart_data' => $daily_costs,
      '#attached' => [
        'library' => [
          'core/drupal.tableselect',
        ],
      ],
    ];
  }

  /**
   * Calculate summary statistics.
   *
   * @param array $nodes
   *   Array of cost metric nodes.
   *
   * @return array
   *   Summary statistics.
   */
  protected function calculateSummary(array $nodes): array {
    $total_cost = 0;
    $total_llm_cost = 0;
    $total_input_tokens = 0;
    $total_output_tokens = 0;
    $count = 0;

    foreach ($nodes as $node) {
      $total_cost += $node->get('field_total_cost_usd')->value ?? 0;
      $total_llm_cost += $node->get('field_llm_cost_usd')->value ?? 0;
      $total_input_tokens += $node->get('field_input_tokens')->value ?? 0;
      $total_output_tokens += $node->get('field_output_tokens')->value ?? 0;
      $count++;
    }

    return [
      'total_articles' => $count,
      'total_cost' => round($total_cost, 4),
      'total_llm_cost' => round($total_llm_cost, 4),
      'average_cost' => $count > 0 ? round($total_cost / $count, 4) : 0,
      'total_input_tokens' => $total_input_tokens,
      'total_output_tokens' => $total_output_tokens,
      'total_tokens' => $total_input_tokens + $total_output_tokens,
    ];
  }

  /**
   * Calculate daily costs for the specified number of days.
   *
   * @param array $nodes
   *   Array of cost metric nodes.
   * @param int $days
   *   Number of days to include.
   *
   * @return array
   *   Daily cost data.
   */
  protected function calculateDailyCosts(array $nodes, int $days): array {
    $daily_costs = [];
    $today = strtotime('today');

    // Initialize array with zeros for each day
    for ($i = $days - 1; $i >= 0; $i--) {
      $date = date('Y-m-d', strtotime("-$i days", $today));
      $daily_costs[$date] = [
        'date' => $date,
        'cost' => 0,
        'count' => 0,
      ];
    }

    // Aggregate costs by day
    foreach ($nodes as $node) {
      $created = (int) $node->getCreatedTime();
      $date = date('Y-m-d', $created);
      
      if (isset($daily_costs[$date])) {
        $daily_costs[$date]['cost'] += $node->get('field_total_cost_usd')->value ?? 0;
        $daily_costs[$date]['count']++;
      }
    }

    // Round costs
    foreach ($daily_costs as &$day) {
      $day['cost'] = round($day['cost'], 4);
    }

    return array_values($daily_costs);
  }

}