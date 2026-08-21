<?php

declare(strict_types=1);

namespace Unknown\CustomItems\command;

use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use Throwable;
use Unknown\CustomItems\CustomItems;
use Unknown\CustomItems\item\ItemDefinition;
use Unknown\CustomItems\libs\customiesdevs\customies\item\CustomiesItemFactory;
use function array_shift;
use function array_slice;
use function count;
use function implode;
use function is_numeric;
use function max;
use function min;
use function strtolower;

/** Comando administrativo: listar, inspecionar e obter os itens customizados. */
final class CustomItemsCommand implements CommandExecutor {

	private const MAX_GIVE_AMOUNT = 1024;

	public function __construct(private readonly CustomItems $plugin) { }

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
		$subCommand = strtolower((string) array_shift($args));

		switch($subCommand){
			case "list":
				$this->sendList($sender);
				return true;
			case "info":
				$this->sendInfo($sender, $args);
				return true;
			case "give":
				$this->give($sender, $args);
				return true;
			case "pack":
				$this->rebuildPack($sender);
				return true;
		}

		$sender->sendMessage(TextFormat::YELLOW . "Uso: /$label <list|info|give|pack>");
		$sender->sendMessage(TextFormat::GRAY . " /$label list " . TextFormat::WHITE . "- lista os itens registrados");
		$sender->sendMessage(TextFormat::GRAY . " /$label info <item> " . TextFormat::WHITE . "- mostra as propriedades de um item");
		$sender->sendMessage(TextFormat::GRAY . " /$label give [jogador] <item> [quantidade] " . TextFormat::WHITE . "- entrega o item");
		$sender->sendMessage(TextFormat::GRAY . " /$label pack " . TextFormat::WHITE . "- regera o resource pack");
		return true;
	}

	private function sendList(CommandSender $sender): void {
		$definitions = $this->plugin->getRegistry()->getAll();
		if($definitions === []) {
			$sender->sendMessage(TextFormat::RED . "Nenhum item customizado está registrado. Confira o items.yml.");
			return;
		}

		$sender->sendMessage(TextFormat::GREEN . count($definitions) . " item(ns) customizado(s):");
		foreach($definitions as $identifier => $definition){
			$sender->sendMessage(TextFormat::GRAY . " - " . TextFormat::WHITE . $identifier . TextFormat::GRAY . " (" . $definition->getDisplayName() . ", " . $definition->getType() . ")");
		}
	}

	/** @param string[] $args */
	private function sendInfo(CommandSender $sender, array $args): void {
		$definition = $this->findDefinition($sender, $args[0] ?? null);
		if($definition === null) {
			return;
		}

		$sender->sendMessage(TextFormat::GREEN . $definition->getDisplayName() . TextFormat::GRAY . " (" . $definition->getIdentifier() . ")");
		$this->sendField($sender, "Tipo", $definition->getType());
		$this->sendField($sender, "Textura", $definition->getTexture());
		$this->sendField($sender, "Pilha máxima", (string) $definition->getMaxStackSize());

		if($definition->getType() === ItemDefinition::TYPE_TOOL || $definition->getType() === ItemDefinition::TYPE_ARMOR) {
			$this->sendField($sender, "Durabilidade", (string) $definition->getMaxDurability());
		}
		if($definition->getAttackDamage() > 0) {
			$this->sendField($sender, "Dano", (string) $definition->getAttackDamage());
		}
		if($definition->getType() === ItemDefinition::TYPE_TOOL) {
			$this->sendField($sender, "Eficiência", (string) $definition->getEfficiency());
			$this->sendField($sender, "Nível de coleta", (string) $definition->getHarvestLevel());
		}

		$food = $definition->getFood();
		if($food !== null) {
			$this->sendField($sender, "Nutrição / saturação", $food["nutrition"] . " / " . $food["saturation"]);
			$this->sendField($sender, "Come sem fome", $food["always_edible"] ? "sim" : "não");
			$this->sendField($sender, "Resto ao comer", $food["residue"] === "" ? "nenhum" : $food["residue"]);
		}

		$armor = $definition->getArmor();
		if($armor !== null) {
			$this->sendField($sender, "Slot", match ($armor["slot"]) {
				0 => "capacete", 1 => "peitoral", 2 => "calça", 3 => "bota", default => "?"
			});
			$this->sendField($sender, "Proteção / tenacidade", $armor["defense"] . " / " . $armor["toughness"]);
		}

		$digger = $definition->getDigger();
		if($digger !== null) {
			$tags = [];
			foreach($digger["destroy_speeds"] as $entry){
				$tags[] = implode("/", $entry["tags"]) . " @" . $entry["speed"];
			}
			$this->sendField($sender, "Digger", implode(", ", $tags));
		}

		if($definition->getFuelTime() > 0) {
			$this->sendField($sender, "Combustível", $definition->getFuelTime() . " ticks");
		}
		if($definition->getRarity() !== null) {
			$this->sendField($sender, "Raridade", $definition->getRarity());
		}
		$this->sendField($sender, "Brilho encantado", $definition->hasGlint() ? "sim" : "não");
		$this->sendField($sender, "Criativo", $definition->getCreativeCategory() . " / " . $definition->getCreativeGroup());
	}

	/** @param string[] $args */
	private function give(CommandSender $sender, array $args): void {
		if(count($args) === 0) {
			$sender->sendMessage(TextFormat::RED . "Uso: /customitems give [jogador] <item> [quantidade]");
			return;
		}

		// O nome do jogador é opcional quando quem digita já está em jogo. Um identificador de
		// item conhecido nunca é lido como nome, para não confundir os dois argumentos.
		if($this->plugin->getRegistry()->has(strtolower($args[0]))) {
			if(!$sender instanceof Player) {
				$sender->sendMessage(TextFormat::RED . "Rode do console informando o jogador: /customitems give <jogador> <item> [quantidade]");
				return;
			}
			$target = $sender;
		} else {
			$target = $this->plugin->getServer()->getPlayerByPrefix($args[0]);
			if($target === null) {
				$sender->sendMessage(TextFormat::RED . "Não existe nenhum jogador online chamado '{$args[0]}' nem um item com esse identificador.");
				return;
			}
			$args = array_slice($args, 1);
		}

		$definition = $this->findDefinition($sender, $args[0] ?? null);
		if($definition === null) {
			return;
		}

		$amount = 1;
		if(isset($args[1])) {
			if(!is_numeric($args[1])) {
				$sender->sendMessage(TextFormat::RED . "A quantidade precisa ser um número.");
				return;
			}
			$amount = min(self::MAX_GIVE_AMOUNT, max(1, (int) $args[1]));
		}

		try{
			$item = CustomiesItemFactory::getInstance()->get($definition->getIdentifier(), $amount);
		}catch(Throwable $e){
			$sender->sendMessage(TextFormat::RED . "Não foi possível criar '{$definition->getIdentifier()}': " . $e->getMessage());
			return;
		}

		// O que não couber no inventário cai no chão, como no /give do próprio jogo.
		foreach($target->getInventory()->addItem($item) as $leftover){
			$target->getWorld()->dropItem($target->getPosition(), $leftover);
		}

		$sender->sendMessage(TextFormat::GREEN . "{$amount}x {$definition->getDisplayName()} entregue para {$target->getName()}.");
		if($target !== $sender) {
			$target->sendMessage(TextFormat::GREEN . "Você recebeu {$amount}x {$definition->getDisplayName()}.");
		}
	}

	private function rebuildPack(CommandSender $sender): void {
		if($this->plugin->getPackGenerator() === null) {
			$sender->sendMessage(TextFormat::RED . "A geração do resource pack está desligada no config.yml.");
			return;
		}

		try{
			$zipPath = $this->plugin->buildResourcePack();
		}catch(Throwable $e){
			$sender->sendMessage(TextFormat::RED . "Falha ao gerar o resource pack: " . $e->getMessage());
			return;
		}
		if($zipPath === null) {
			$sender->sendMessage(TextFormat::RED . "Nenhuma textura encontrada em " . $this->plugin->getPackGenerator()->getSourceTexturesPath() . ".");
			return;
		}

		$this->plugin->registerResourcePack($zipPath);
		$sender->sendMessage(TextFormat::GREEN . "Resource pack regerado em $zipPath.");
		$sender->sendMessage(TextFormat::YELLOW . "Quem já está conectado precisa entrar de novo para baixar a versão nova.");
	}

	private function findDefinition(CommandSender $sender, ?string $identifier): ?ItemDefinition {
		if($identifier === null) {
			$sender->sendMessage(TextFormat::RED . "Informe o identificador do item. Use /customitems list para ver os disponíveis.");
			return null;
		}

		$definition = $this->plugin->getRegistry()->get(strtolower($identifier));
		if($definition === null) {
			$sender->sendMessage(TextFormat::RED . "O item '$identifier' não está registrado. Use /customitems list para ver os disponíveis.");
			return null;
		}
		return $definition;
	}

	private function sendField(CommandSender $sender, string $label, string $value): void {
		$sender->sendMessage(TextFormat::GRAY . " $label: " . TextFormat::WHITE . $value);
	}
}
