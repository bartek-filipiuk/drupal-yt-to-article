<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\ValueObject;

/**
 * Immutable value object representing an article generation API response.
 *
 * This object encapsulates the response data from the PocketFlow API when
 * requesting article generation from a YouTube video. It provides a type-safe
 * way to access response properties and includes utility methods for checking
 * the response status.
 *
 * Status values:
 * - 'pending': Request received, processing not yet started
 * - 'processing': Article generation in progress
 * - 'completed': Article successfully generated
 * - 'failed': Article generation failed (check error property)
 *
 * Example usage:
 * @code
 * // Creating from API response array
 * $response = ArticleResponse::fromArray([
 *   'request_id' => 'abc-123',
 *   'status' => 'completed',
 *   'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
 *   'metadata' => ['duration' => 212, 'title' => 'Video Title'],
 * ]);
 *
 * // Checking response status
 * if ($response->isSuccessful()) {
 *   // Process successful response
 * }
 *
 * if ($response->hasError()) {
 *   $errorMessage = $response->error;
 * }
 * @endcode
 */
final readonly class ArticleResponse {

  /**
   * Known status values for validation.
   */
  private const VALID_STATUSES = ['pending', 'processing', 'completed', 'failed'];

  /**
   * Constructs an ArticleResponse value object.
   *
   * @param string $requestId
   *   Unique identifier for this article generation request.
   * @param string $status
   *   Current status of the request (pending, processing, completed, failed).
   * @param string|null $youtubeUrl
   *   The YouTube URL being processed, if available.
   * @param string|null $error
   *   Error message if the request failed, NULL otherwise.
   * @param array|null $metadata
   *   Additional metadata about the request or generated article.
   */
  public function __construct(
    public string $requestId,
    public string $status,
    public ?string $youtubeUrl = null,
    public ?string $error = null,
    public ?array $metadata = null,
  ) {}

  /**
   * Creates an ArticleResponse from API response data.
   *
   * This factory method handles multiple possible field name formats that
   * the API might return (request_id, requestId, or id) for flexibility.
   *
   * @param array $data
   *   The API response data array. Must contain at least one of:
   *   'request_id', 'requestId', or 'id'.
   *
   * @return self
   *   A new ArticleResponse instance.
   *
   * @throws \InvalidArgumentException
   *   When the required request_id field is missing from the data.
   */
  public static function fromArray(array $data): self {
    $requestId = $data['request_id']
      ?? $data['requestId']
      ?? $data['id']
      ?? NULL;

    if ($requestId === NULL || $requestId === '') {
      throw new \InvalidArgumentException(
        sprintf(
          'Missing required field: request_id (or requestId/id). Received fields: [%s]',
          implode(', ', array_keys($data))
        )
      );
    }

    $status = $data['status'] ?? 'pending';

    if (!in_array($status, self::VALID_STATUSES, TRUE)) {
      // Log warning but allow unknown statuses for forward compatibility.
      @trigger_error(
        sprintf('Unknown status "%s" received. Expected one of: %s', $status, implode(', ', self::VALID_STATUSES)),
        E_USER_NOTICE
      );
    }

    return new self(
      requestId: (string) $requestId,
      status: $status,
      youtubeUrl: $data['youtube_url'] ?? NULL,
      error: $data['error'] ?? NULL,
      metadata: $data['metadata'] ?? NULL,
    );
  }

  /**
   * Checks if the response indicates successful completion.
   *
   * A response is considered successful only when:
   * - The status is 'completed'
   * - No error message is present
   *
   * @return bool
   *   TRUE if the article was successfully generated, FALSE otherwise.
   */
  public function isSuccessful(): bool {
    return $this->status === 'completed' && $this->error === NULL;
  }

  /**
   * Checks if the response indicates an error occurred.
   *
   * A response is considered to have an error when either:
   * - An error message is present
   * - The status is 'failed'
   *
   * @return bool
   *   TRUE if an error occurred, FALSE otherwise.
   */
  public function hasError(): bool {
    return $this->error !== NULL || $this->status === 'failed';
  }

  /**
   * Converts the response to an array representation.
   *
   * Useful for serialization or logging purposes.
   *
   * @return array
   *   Array representation of this response.
   */
  public function toArray(): array {
    return [
      'request_id' => $this->requestId,
      'status' => $this->status,
      'youtube_url' => $this->youtubeUrl,
      'error' => $this->error,
      'metadata' => $this->metadata,
    ];
  }

}
