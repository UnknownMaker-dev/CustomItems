<?php

declare(strict_types=1);

namespace Unknown\CustomItems\item;

use pocketmine\entity\Living;
use pocketmine\item\Food;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use customiesdevs\customies\item\ItemComponents;

/** Item customizado comestível. */
final class CustomFood extends Food implements ItemComponents {
	use CustomItemTrait;

	public function getFoodRestore(): int {
		return $this->definition->getFood()["nutrition"];
	}

	public function getSaturationRestore(): float {
		return $this->definition->getFood()["saturation"];
	}

	public function requiresHunger(): bool {
		return !$this->definition->getFood()["always_edible"];
	}

	public function getMinUseDuration(): int {
		// O cliente libera o consumo depois de eat_ticks; com o padrão de 32 do PocketMine, uma comida
		// mais rápida que isso seria recusada pelo servidor.
		return $this->definition->getFood()["eat_ticks"];
	}

	public function getAdditionalEffects(): array {
		$food = $this->definition->getFood();
		// Com clear_effects os efeitos vão no onConsume: o PocketMine aplica esta lista antes de
		// chamar o onConsume, e a limpeza levaria junto os efeitos da própria comida.
		return $food["clear_effects"] ? [] : Effects::roll($food["effects"]);
	}

	public function onConsume(Living $consumer): void {
		$food = $this->definition->getFood();
		if($food["clear_effects"]) {
			$consumer->getEffects()->clear();
			Effects::apply($consumer, $food["effects"]);
		}
	}

	/** O que sobra na mão depois de comer, tipo a tigela do ensopado. */
	public function getResidue(): Item {
		$residue = $this->definition->getFood()["residue"];
		if($residue === "") {
			return VanillaItems::AIR();
		}
		// Id inválido já foi reportado no boot pelo ItemRegistry; aqui só não devolvemos nada.
		return StringToItemParser::getInstance()->parse($residue) ?? VanillaItems::AIR();
	}
}
