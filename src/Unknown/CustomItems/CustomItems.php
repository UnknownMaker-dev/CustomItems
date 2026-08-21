<?php

declare(strict_types=1);

namespace Unknown\CustomItems;

use pocketmine\plugin\PluginBase;
use pocketmine\resourcepacks\ZippedResourcePack;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Filesystem\Path;
use Throwable;
use Unknown\CustomItems\command\CustomItemsCommand;
use Unknown\CustomItems\item\ItemDefinition;
use Unknown\CustomItems\item\ItemDefinitionException;
use Unknown\CustomItems\item\ItemRegistry;
use Unknown\CustomItems\libs\customiesdevs\customies\CustomiesListener;
use Unknown\CustomItems\resourcepack\ResourcePackGenerator;
use function array_unshift;
use function array_values;
use function class_exists;
use function count;
use function is_array;
use function is_int;
use function is_string;
use function method_exists;
use function strrpos;
use function strtolower;
use function substr;

/**
 * Cria itens customizados a partir do items.yml usando o Customies, e monta o resource pack
 * que dá ícone a eles.
 */
final class CustomItems extends PluginBase {
	use SingletonTrait;

	private ItemRegistry $registry;
	private ?ResourcePackGenerator $packGenerator = null;

	protected function onLoad(): void {
		self::setInstance($this);
	}

	protected function onEnable(): void {
		$conflict = $this->findConflictingCustomies();
		if($conflict !== null) {
			$this->getLogger()->emergency("$conflict está instalado, mas o CustomItems traz o Customies embutido. Duas cópias do Customies brigam pela palette e deixam blocos ou itens invisíveis para o cliente. Use a versão CustomItems-NoLibs junto do Customies avulso.");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		$this->saveDefaultConfig();
		$this->saveResource("items.yml");

		// Papel que era do onEnable do Customies: este listener liga o experimento data_driven_items,
		// sem ele o cliente mostra os itens customizados como itens desconhecidos.
		$this->getServer()->getPluginManager()->registerEvents(new CustomiesListener(), $this);

		$this->registry = new ItemRegistry($this->getLogger());
		foreach($this->loadDefinitions() as $definition){
			try{
				$this->registry->register($definition);
			}catch(Throwable $e){
				$this->getLogger()->error("Não foi possível registrar '{$definition->getIdentifier()}': " . $e->getMessage());
			}
		}
		$this->getLogger()->info($this->registry->count() . " item(ns) customizado(s) registrado(s).");

		$this->setupResourcePack();

		$command = $this->getCommand("customitems");
		if($command !== null) {
			$command->setExecutor(new CustomItemsCommand($this));
		}

		// Delay de 0 tick: roda assim que o servidor termina de subir, depois que todo plugin que
		// depende do CustomItems já teve seu onEnable. A partir daqui a tabela está fechada.
		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (): void {
			$this->registry->lock();
		}), 0);
	}

	public function getRegistry(): ItemRegistry {
		return $this->registry;
	}

	public function getPackGenerator(): ?ResourcePackGenerator {
		return $this->packGenerator;
	}

	/**
	 * Registra um item a partir de um array no mesmo formato do items.yml.
	 *
	 * Ponto de entrada para outros plugins; precisa ser chamado no onEnable deles, com
	 * `depend`/`softdepend` no CustomItems para garantir a ordem.
	 *
	 * @param mixed[] $data
	 * @throws ItemDefinitionException se a definição estiver inválida
	 */
	public function registerItem(string $identifier, array $data): ItemDefinition {
		$definition = ItemDefinition::parse($identifier, $data);
		$this->registry->register($definition);
		return $definition;
	}

	/**
	 * Procura outra cópia ativa do Customies.
	 *
	 * Além do plugin avulso, varremos os plugins carregados atrás de um Customies embutido em
	 * `libs/` — a convenção de virion do Poggit — para pegar também plugins de terceiros.
	 */
	private function findConflictingCustomies(): ?string {
		$manager = $this->getServer()->getPluginManager();
		if($manager->getPlugin("Customies") !== null) {
			return "O plugin Customies avulso";
		}

		foreach($manager->getPlugins() as $plugin){
			if($plugin === $this) {
				continue;
			}
			$main = $plugin->getDescription()->getMain();
			$separator = strrpos($main, "\\");
			if($separator === false) {
				continue;
			}
			if(class_exists(substr($main, 0, $separator) . "\\libs\\customiesdevs\\customies\\CustomiesListener")) {
				return "O plugin " . $plugin->getName();
			}
		}
		return null;
	}

	/**
	 * Lê o items.yml e transforma cada entrada em uma definição validada.
	 *
	 * @return ItemDefinition[]
	 */
	private function loadDefinitions(): array {
		$config = new Config(Path::join($this->getDataFolder(), "items.yml"), Config::YAML, []);
		$items = $config->get("items", []);
		if(!is_array($items)) {
			$this->getLogger()->error("A chave 'items' do items.yml precisa ser um mapa de 'namespace:nome' para as propriedades do item.");
			return [];
		}

		$definitions = [];
		foreach($items as $identifier => $data){
			if(!is_array($data)) {
				$this->getLogger()->error("A entrada '$identifier' do items.yml está vazia ou não é um mapa de propriedades.");
				continue;
			}
			try{
				$definitions[] = ItemDefinition::parse((string) $identifier, $data);
			}catch(ItemDefinitionException $e){
				$this->getLogger()->error($e->getMessage());
			}
		}
		return $definitions;
	}

	/** Gera o resource pack e, se configurado, o coloca no topo da pilha do servidor. */
	private function setupResourcePack(): void {
		$config = $this->getConfig();
		if($config->getNested("resource-pack.generate", true) !== true) {
			return;
		}

		$this->packGenerator = new ResourcePackGenerator($this->getLogger(), $this->getDataFolder());
		// O caminho muda conforme 'plugins.legacy-data-dir' do pocketmine.yml, então vale dizer
		// exatamente onde os PNGs devem ficar neste servidor.
		$this->getLogger()->info("Ícones dos itens: " . $this->packGenerator->getSourceTexturesPath());

		try{
			$zipPath = $this->buildResourcePack();
		}catch(Throwable $e){
			$this->getLogger()->error("Falha ao gerar o resource pack: " . $e->getMessage());
			return;
		}
		if($zipPath === null) {
			return;
		}

		if($config->getNested("resource-pack.auto-register", true) !== true) {
			$this->getLogger()->info("Resource pack pronto em $zipPath. Como o auto-register está desligado, adicione-o manualmente em resource_packs/.");
			return;
		}
		$this->registerResourcePack($zipPath);
	}

	/** @return string|null caminho do .mcpack, ou null se não havia textura para empacotar */
	public function buildResourcePack(): ?string {
		if($this->packGenerator === null) {
			return null;
		}
		$config = $this->getConfig();
		return $this->packGenerator->generate(
			array_values($this->registry->getAll()),
			(string) $config->getNested("resource-pack.name", "CustomItems"),
			(string) $config->getNested("resource-pack.description", "Ícones dos itens customizados"),
			$this->readOrCreateUuid("resource-pack.uuid"),
			$this->readOrCreateUuid("resource-pack.module-uuid"),
			$this->readVersion("resource-pack.min-engine-version", [1, 21, 0])
		);
	}

	public function registerResourcePack(string $zipPath): void {
		$manager = $this->getServer()->getResourcePackManager();
		if(!method_exists($manager, "setResourceStack")) {
			$this->getLogger()->warning("Esta versão do PocketMine-MP não permite alterar a lista de resource packs em tempo de execução. Copie $zipPath para a pasta resource_packs/ do servidor.");
			return;
		}

		try{
			$pack = new ZippedResourcePack($zipPath);
		}catch(Throwable $e){
			$this->getLogger()->error("O resource pack gerado foi recusado pelo servidor: " . $e->getMessage());
			return;
		}

		$packId = strtolower($pack->getPackId());
		$stack = [];
		foreach($manager->getResourceStack() as $existing){
			// Substitui a versão anterior do nosso próprio pack em vez de duplicá-la.
			if(strtolower($existing->getPackId()) !== $packId) {
				$stack[] = $existing;
			}
		}
		array_unshift($stack, $pack);
		$manager->setResourceStack($stack);

		if($this->getConfig()->getNested("resource-pack.force-resources", false) === true) {
			$manager->setResourcePacksRequired(true);
		} elseif(!$manager->resourcePacksRequired()) {
			$this->getLogger()->notice("O resource pack foi adicionado, mas o servidor não exige resource packs: quem recusar vai ver os itens customizados sem ícone. Ligue 'resource-pack.force-resources' no config.yml para exigir.");
		}

		$this->getLogger()->info("Resource pack '{$pack->getPackName()}' v{$pack->getPackVersion()} adicionado à pilha do servidor.");
	}

	/** Lê um UUID do config, gerando e salvando um novo na primeira execução. */
	private function readOrCreateUuid(string $key): string {
		$config = $this->getConfig();
		$uuid = $config->getNested($key);
		if(is_string($uuid) && Uuid::isValid($uuid)) {
			return $uuid;
		}

		$uuid = Uuid::uuid4()->toString();
		$config->setNested($key, $uuid);
		$config->save();
		return $uuid;
	}

	/**
	 * @param int[] $default
	 * @return int[]
	 */
	private function readVersion(string $key, array $default): array {
		$version = $this->getConfig()->getNested($key);
		if(!is_array($version) || count($version) !== 3) {
			return $default;
		}
		$result = [];
		foreach(array_values($version) as $part){
			if(!is_int($part)) {
				return $default;
			}
			$result[] = $part;
		}
		return $result;
	}
}
