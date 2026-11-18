<?php

namespace App\Exceptions;

use RuntimeException;

/** Falha ao falar com a Railway Public API (rede, token recusado, erro GraphQL). */
class RailwayApiException extends RuntimeException {}
