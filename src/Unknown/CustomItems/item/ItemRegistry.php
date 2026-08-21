<?php

declare(strict_types=1);

namespace Unknown\CustomItems\item;

use Logger;
use LogicException;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use Unknown\CustomItems\libs\customiesdevs\customies\item\CustomiesItemFactory;
use function array_key_exists;
use function count;

/**
 * Guarda as definições registradas e entrega cada item ao Customies.
 *
 * O registro precisa acontecer inteiro dentro do onEnable: depois que o servidor sobe, a tabela de
 * itens já foi enviada ao cliente e não aceita entradas novas.
 */
final class ItemRegistry {

	/** @var array<string, ItemDefinition> */
	private array $definitions = [];
	private bool $locked = false;

	public function __construct(private readonly Logger $logger) { }

	/**
	 * Registra um item no Customies.
	 *
	 * @throws LogicException se o registro já estiver fechado ou o identificador estiver em uso
	 */
	public function register(ItemDefinition $definition): void {
		$identifier = $definition->getIdentifier();
		if($this->locked) {
			throw new LogicException("Itens customizados só podem ser registrados durante o onEnable (tentativa com '$identifier')");
		}
		if(array_key_exists($identifier, $this->definitions)) {
			throw new LogicException("O item '$identifier' já está registrado");
		}

		$this->warnAboutUnknownResidue($definition);

		// Diferente dos blocos, a closure de item é chamada uma única vez e na thread principal —
		// o Customies não replica itens nos AsyncWorkers. Por isso dá para capturar o objeto direto.
		$itemFunc = static fn(): Item => match ($definition->getType()) {
			ItemDefinition::TYPE_FOOD => new CustomFood($definition),
			ItemDefinition::TYPE_TOOL => new CustomTool($definition),
			ItemDefinition::TYPE_ARMOR => new CustomArmor($definition),
			default => new CustomItem($definition),
		};

		CustomiesItemFactory::getInstance()->registerItem($itemFunc, $identifier, $definition->createCreativeInfo());
		$this->definitions[$identifier] = $definition;
	}

	/** Fecha o registro. Chamado quando o servidor termina de subir. */
	public function lock(): void {
		$this->locked = true;
	}

	public function isLocked(): bool {
		return $this->locked;
	}

	public function has(string $identifier): bool {
		return array_key_exists($identifier, $this->definitions);
	}

	public function get(string $identifier): ?ItemDefinition {
		return $this->definitions[$identifier] ?? null;
	}

	/** @return array<string, ItemDefinition> */
	public function getAll(): array {
		return $this->definitions;
	}

	public function count(): int {
		return count($this->definitions);
	}

	/**
	 * O resto que sobra ao comer só é resolvido na hora, então um id errado passaria despercebido
	 * até alguém comer o item. Avisamos aqui, no boot, enquanto ainda dá para corrigir.
	 */
	private function warnAboutUnknownResidue(ItemDefinition $definition): void {
		$food = $definition->getFood();
		if($food === null || $food["residue"] === "") {
			return;
		}
		if(StringToItemParser::getInstance()->parse($food["residue"]) === null) {
			$this->logger->warning("O item '{$definition->getIdentifier()}' tem o residue '{$food["residue"]}', que não corresponde a nenhum item conhecido e será ignorado.");
		}
	}
}
