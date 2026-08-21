<?php

declare(strict_types=1);

namespace Unknown\CustomItems\item;

use RuntimeException;
use Throwable;

/** Lançada quando uma entrada do items.yml não pode ser transformada em um item válido. */
final class ItemDefinitionException extends RuntimeException {

	public function __construct(private readonly string $identifier, string $reason, ?Throwable $previous = null) {
		parent::__construct("Item '$identifier': $reason", 0, $previous);
	}

	public function getIdentifier(): string {
		return $this->identifier;
	}
}
