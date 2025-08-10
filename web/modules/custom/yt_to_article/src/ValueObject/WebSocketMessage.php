<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\ValueObject;

/**
 * Value object representing a WebSocket message.
 */
final readonly class WebSocketMessage {
  
  public function __construct(
    public string $stage,
    public int $progress,
    public string $message,
    public array $details = [],
    public ?string $error = null,
  ) {}
  
  /**
   * Create from JSON string.
   */
  public static function fromJson(string $json): self {
    try {
      $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
      throw new \InvalidArgumentException('Invalid JSON: ' . $e->getMessage(), 0, $e);
    }
    
    return self::fromArray($data);
  }
  
  /**
   * Create from array data.
   */
  public static function fromArray(array $data): self {
    return new self(
      stage: $data['stage'] ?? throw new \InvalidArgumentException('Missing stage'),
      progress: (int) ($data['progress'] ?? 0),
      message: $data['message'] ?? '',
      details: $data['details'] ?? [],
      error: $data['error'] ?? null,
    );
  }
  
  /**
   * Check if this is a completion message.
   */
  public function isComplete(): bool {
    return $this->stage === 'finished' || $this->stage === 'completed';
  }
  
  /**
   * Check if this is an error message.
   */
  public function isError(): bool {
    return $this->error !== null || $this->stage === 'error' || $this->stage === 'failed';
  }
  
  /**
   * Convert to array for JSON encoding.
   */
  public function toArray(): array {
    return [
      'stage' => $this->stage,
      'progress' => $this->progress,
      'message' => $this->message,
      'details' => $this->details,
      'error' => $this->error,
    ];
  }
}