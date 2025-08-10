<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\Exception;

/**
 * Exception thrown when user has insufficient funds (credits or balance).
 */
final class InsufficientFundsException extends ApiException {
  
  /**
   * Current credits available.
   */
  private int $currentCredits;
  
  /**
   * Current balance available.
   */
  private float $currentBalance;
  
  /**
   * Minimum balance required.
   */
  private float $minimumBalance;
  
  /**
   * Constructor.
   *
   * @param string $message
   *   The exception message.
   * @param int $currentCredits
   *   Current credits available.
   * @param float $currentBalance
   *   Current balance available.
   * @param float $minimumBalance
   *   Minimum balance required.
   * @param int $code
   *   The exception code (default 402).
   * @param \Throwable|null $previous
   *   The previous throwable.
   */
  public function __construct(
    string $message = '',
    int $currentCredits = 0,
    float $currentBalance = 0.0,
    float $minimumBalance = 0.30,
    int $code = 402,
    ?\Throwable $previous = null
  ) {
    parent::__construct($message, $code, [], $previous);
    $this->currentCredits = $currentCredits;
    $this->currentBalance = $currentBalance;
    $this->minimumBalance = $minimumBalance;
  }
  
  /**
   * Get current credits.
   *
   * @return int
   */
  public function getCurrentCredits(): int {
    return $this->currentCredits;
  }
  
  /**
   * Get current balance.
   *
   * @return float
   */
  public function getCurrentBalance(): float {
    return $this->currentBalance;
  }
  
  /**
   * Get minimum balance required.
   *
   * @return float
   */
  public function getMinimumBalance(): float {
    return $this->minimumBalance;
  }
  
  /**
   * Check if user has no funds at all.
   *
   * @return bool
   */
  public function hasNoFunds(): bool {
    return $this->currentCredits === 0 && $this->currentBalance < $this->minimumBalance;
  }
}