<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a block to display recent articles for anonymous users.
 *
 * @Block(
 *   id = "yt_to_article_anonymous_articles",
 *   admin_label = @Translation("Your Recent Articles (Session)"),
 *   category = @Translation("YT to Article"),
 * )
 */
class AnonymousArticlesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Constructs a new AnonymousArticlesBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AccountProxyInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    RequestStack $request_stack
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Only show for anonymous users
    if (!$this->currentUser->isAnonymous()) {
      return [];
    }

    $session = $this->requestStack->getCurrentRequest()->getSession();
    $anonymousData = $session->get('yt_to_article_anonymous', ['articles' => []]);
    $articles = $anonymousData['articles'] ?? [];

    if (empty($articles)) {
      return [
        '#markup' => '<div class="yt-to-article-no-articles">' .
                    $this->t('You haven\'t generated any articles yet.') .
                    '</div>',
        '#cache' => [
          'contexts' => ['session'],
          'max-age' => 0,
        ],
      ];
    }

    $items = [];
    $storage = $this->entityTypeManager->getStorage('node');

    foreach ($articles as $article) {
      $requestId = $article['request_id'] ?? '';
      $timestamp = $article['timestamp'] ?? 0;
      $youtubeUrl = $article['youtube_url'] ?? '';
      $nodeId = $article['node_id'] ?? NULL;
      $title = $article['title'] ?? NULL;

      // Try to load the node if we have an ID
      if ($nodeId) {
        $node = $storage->load($nodeId);
        if ($node) {
          $url = Url::fromRoute('entity.node.canonical', ['node' => $nodeId]);
          $items[] = [
            '#type' => 'link',
            '#title' => $title ?: $node->label(),
            '#url' => $url,
            '#attributes' => [
              'class' => ['yt-to-article-link'],
            ],
          ];
          continue;
        }
      }

      // If no node yet, try to find it by request_id
      if ($requestId) {
        // Check if field exists first
        $fieldDefinitions = \Drupal::service('entity_field.manager')
          ->getFieldDefinitions('node', 'youtube_article');

        if (isset($fieldDefinitions['field_request_id'])) {
          $query = $storage->getQuery()
            ->condition('type', 'youtube_article')
            ->condition('field_request_id', $requestId)
            ->accessCheck(TRUE)
            ->range(0, 1);

          $nids = $query->execute();

          if (!empty($nids)) {
            $nid = reset($nids);
            $node = $storage->load($nid);

            if ($node) {
              // Update session with node info
              $this->updateSessionArticle($requestId, (int) $nid, $node->label());

              $url = Url::fromRoute('entity.node.canonical', ['node' => $nid]);
              $items[] = [
                '#type' => 'link',
                '#title' => $node->label(),
                '#url' => $url,
                '#attributes' => [
                  'class' => ['yt-to-article-link'],
                ],
              ];
              continue;
            }
          }
        }
      }

      // Still processing or not found
      $items[] = [
        '#markup' => '<div class="yt-to-article-processing">' .
                    $this->t('Processing: @url', ['@url' => substr($youtubeUrl, 0, 50) . '...']) .
                    '<div class="yt-to-article-meta">' .
                    $this->t('Started @time ago', [
                      '@time' => $this->formatTimeDiff($timestamp)
                    ]) . '</div></div>',
      ];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => [
        'class' => ['yt-to-article-anonymous-list'],
      ],
      '#cache' => [
        'contexts' => ['session'],
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Format time difference in human-readable format.
   *
   * @param int $timestamp
   *   The timestamp to compare.
   *
   * @return string
   *   Formatted time difference.
   */
  protected function formatTimeDiff(int $timestamp): string {
    $diff = time() - $timestamp;

    if ($diff < 60) {
      return (string) $this->t('@count seconds', ['@count' => $diff]);
    }
    elseif ($diff < 3600) {
      return (string) $this->t('@count minutes', ['@count' => round($diff / 60)]);
    }
    elseif ($diff < 86400) {
      return (string) $this->t('@count hours', ['@count' => round($diff / 3600)]);
    }
    else {
      return (string) $this->t('@count days', ['@count' => round($diff / 86400)]);
    }
  }

  /**
   * Update session with node information.
   *
   * @param string $requestId
   *   The request ID.
   * @param int $nodeId
   *   The node ID.
   * @param string $title
   *   The node title.
   */
  protected function updateSessionArticle(string $requestId, int $nodeId, string $title): void {
    $session = $this->requestStack->getCurrentRequest()->getSession();
    $anonymousData = $session->get('yt_to_article_anonymous', ['articles' => []]);

    foreach ($anonymousData['articles'] as &$article) {
      if ($article['request_id'] === $requestId) {
        $article['node_id'] = $nodeId;
        $article['title'] = $title;
        break;
      }
    }

    $session->set('yt_to_article_anonymous', $anonymousData);
  }

}
