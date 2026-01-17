<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\ValueObject;

/**
 * Value object representing a WebSocket message.
 *
 * Supports both the new nested format (type + data) and legacy flat format.
 * New format: {"type": "error", "data": {"message": "...", "error_code": "..."}}
 * Legacy format: {"stage": "...", "progress": ..., "message": "..."}
 */
final readonly class WebSocketMessage {

  public function __construct(
    public string $type,
    public string $stage,
    public int $progress,
    public string $message,
    public array $details = [],
    public ?string $error = null,
    public ?string $errorCode = null,
  ) {}

  /**
   * Create from JSON string.
   */
  public static function fromJson(string $json): self {
    try {
      $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new \InvalidArgumentException('Invalid JSON: ' . $e->getMessage(), 0, $e);
    }

    return self::fromArray($data);
  }

  /**
   * Create from array data.
   *
   * Handles both new nested format and legacy flat format.
   */
  public static function fromArray(array $data): self {
    // Detect message type from root level (new format) or infer from structure.
    $type = $data['type'] ?? 'progress';

    // Extract inner data - new format nests data, legacy format is flat.
    $innerData = $data['data'] ?? $data;

    // For error messages, stage defaults to 'error'.
    $stage = $innerData['stage'] ?? ($type === 'error' ? 'error' : 'unknown');

    // Handle details - can be array or string in new error format.
    $details = $innerData['details'] ?? [];
    if (is_string($details)) {
      $details = ['info' => $details];
    }

    // For error type, the message becomes the error.
    $error = null;
    if ($type === 'error') {
      $error = $innerData['message'] ?? null;
    }
    elseif (isset($innerData['error'])) {
      $error = $innerData['error'];
    }

    return new self(
      type: $type,
      stage: $stage,
      progress: (int) ($innerData['progress'] ?? 0),
      message: $innerData['message'] ?? '',
      details: $details,
      error: $error,
      errorCode: $innerData['error_code'] ?? null,
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
    return $this->type === 'error'
      || $this->error !== null
      || $this->stage === 'error'
      || $this->stage === 'failed';
  }

  /**
   * Get a user-friendly error message based on error code.
   */
  public function getErrorMessage(): string {
    if (!$this->isError()) {
      return '';
    }

    $errorMessages = [
      'TRANSCRIPT_UNAVAILABLE' => 'This video has no transcript available. Try a video with captions enabled.',
      'VIDEO_PRIVATE' => 'This video is private. Please use a public video.',
      'VIDEO_NOT_FOUND' => 'Video not found. Please check the URL.',
      'INVALID_URL' => 'Invalid YouTube URL format.',
      'TRANSCRIPTION_FAILED' => 'Transcription service error. Please try again later.',
      'LLM_GENERATION_FAILED' => 'AI generation error. Please try again later.',
      'INTERNAL_ERROR' => 'An unexpected error occurred. Please try again later.',
    ];

    if ($this->errorCode && isset($errorMessages[$this->errorCode])) {
      return $errorMessages[$this->errorCode];
    }

    return $this->error ?? $this->message ?? 'An error occurred';
  }

  /**
   * Convert to array for JSON encoding.
   */
  public function toArray(): array {
    return [
      'type' => $this->type,
      'stage' => $this->stage,
      'progress' => $this->progress,
      'message' => $this->message,
      'details' => $this->details,
      'error' => $this->error,
      'error_code' => $this->errorCode,
    ];
  }

}