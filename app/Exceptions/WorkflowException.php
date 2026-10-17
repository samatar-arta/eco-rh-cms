<?php

namespace App\Exceptions;

use RuntimeException;

/** Transition ou état de workflow invalide (≠ refus d'autorisation). */
class WorkflowException extends RuntimeException {}
