<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A payment submission or review that breaks a business rule (over the balance, already
 * reviewed, self-approval). The message is written for the person using the screen.
 */
class PaymentReviewException extends RuntimeException {}
