<?php

namespace App\Exceptions;

use RuntimeException;

/** Violation d'une règle Paylist (autorisation, fichier invalide, destinataire non autorisé). */
class PaylistException extends RuntimeException {}
