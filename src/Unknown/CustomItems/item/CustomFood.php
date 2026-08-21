<?php

declare(strict_types=1);

namespace Unknown\CustomItems\item;

use pocketmine\item\Food;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use Unknown\CustomItems\libs\customiesdevs\customies\item\ItemComponents;

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
