<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\ValueObject;

/**
 * Value object representing an article generation response.
 */
final readonly class ArticleResponse {
  
  public function __construct(
    public string $requestId,
    public string $status,
    public ?string $youtubeUrl = null,
    public ?string $error = null,
    public ?array $metadata = null,
  ) {}
  
  /**
   * Create from API response data.
   */
  public static function fromArray(array $data): self {
    // Handle different possible field names for request ID
    $requestId = $data['request_id'] 
      ?? $data['requestId'] 
      ?? $data['id'] 
      ?? throw new \InvalidArgumentException('Missing request_id. API response: ' . json_encode($data));
    
    return new self(
      requestId: $requestId,
      status: $data['status'] ?? 'pending',
      youtubeUrl: $data['youtube_url'] ?? null,
      error: $data['error'] ?? null,
      metadata: $data['metadata'] ?? null,
    );
  }
  
  /**
   * Check if the response indicates success.
   */
  public function isSuccessful(): bool {
    return $this->status === 'completed' && $this->error === null;
  }
  
  /**
   * Check if the response indicates an error.
   */
  public function hasError(): bool {
    return $this->error !== null || $this->status === 'failed';
  }
}