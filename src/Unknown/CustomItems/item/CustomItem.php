<?php

declare(strict_types=1);

namespace Unknown\CustomItems\item;

use pocketmine\item\Item;
use Unknown\CustomItems\libs\customiesdevs\customies\item\ItemComponents;

/** Item customizado comum: material, moeda, troféu, qualquer coisa sem comportamento especial. */
final class CustomItem extends Item implements ItemComponents {
	use CustomItemTrait;
}
