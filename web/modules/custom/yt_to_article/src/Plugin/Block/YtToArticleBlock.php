<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'YT to Article' Block.
 *
 * @Block(
 *   id = "yt_to_article_block",
 *   admin_label = @Translation("YT to Article form"),
 *   category = @Translation("Forms"),
 * )
 */
final class YtToArticleBlock extends BlockBase implements ContainerFactoryPluginInterface {
  
  /**
   * The form builder.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly FormBuilderInterface $formBuilder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      configuration: $configuration,
      plugin_id: $plugin_id,
      plugin_definition: $plugin_definition,
      formBuilder: $container->get('form_builder'),
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return $this->formBuilder->getForm('Drupal\yt_to_article\Form\YtToArticleForm');
  }
}