<?php
declare(strict_types=1);

namespace Run;

use OnixUI\event\UIOpenEvent;
use OnixUtils\OnixUtils;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\Position;
use Run\form\RunForm;

class RunPlugin extends PluginBase implements Listener{
	use SingletonTrait;

	/** @var Run|null */
	protected ?Run $runRoom = null;

	/** @var Config */
	protected Config $config;

	protected array $db = [];

	/** @var Position[] */
	protected array $mode = [];

	protected function onLoad() : void{
		self::setInstance($this);
	}

	protected function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->config = new Config($this->getDataFolder() . "Config.yml", Config::YAML, [
			"rooms" => []
		]);
		$this->db = $this->config->getAll();

		if(count($this->db["rooms"]) > 0){
			$this->runRoom = new Run();
			$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
				$this->runRoom->tick();
			}), 20);
		}
	}

	protected function onDisable() : void{
		$this->config->setAll($this->db);
		$this->config->save();
	}

	public function randomMap() : array{
		//return $this->db["rooms"][array_rand(array_keys($this->db["rooms"]))];
		$arr = [];
		foreach($this->db["rooms"] as $name => $_){
			$arr[] = $name;
		}

		$random = $arr[array_rand($arr)];

		return $this->db["rooms"][$random];
	}

	public function getRun() : ?Run{
		return $this->runRoom;
	}

	public function addMap(string $name, Position $start, Position $end){
		$this->db["rooms"][$name] = [
			"name" => $name,
			"spawnX" => $start->getX(),
			"spawnY" => $start->getY(),
			"spawnZ" => $start->getZ(),
			"endX" => $end->getX(),
			"endY" => $end->getY(),
			"endZ" => $end->getZ(),
			"world" => $start->getWorld()->getFolderName()
		];
		if(!$this->runRoom instanceof Run){
			$this->runRoom = new Run();
			$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
				$this->runRoom->tick();
			}), 20);
		}
	}

	public function handleInteract(PlayerInteractEvent $event){
		$player = $event->getPlayer();
		if(isset($this->mode[$player->getName()])){
			$mode = $this->mode[$player->getName()]["mode"];
			$name = $this->mode[$player->getName()]["name"];
			if($mode === 1){
				$this->mode[$player->getName()]["start"] = $event->getBlock()->getPosition();
				OnixUtils::message($player, "끝 지점도 터치해주세요.");
				$this->mode[$player->getName()]["mode"] = 2;
				return;
			}

			if($mode === 2){
				$start = $this->mode[$player->getName()]["start"];
				$end = $event->getBlock()->getPosition();
				$this->addMap($name, $start, $end);
				OnixUtils::message($player, "추가되었습니다.");
				unset($this->mode[$player->getName()]);
			}
		}

		if($this->runRoom instanceof Run){
			if($this->runRoom->isRunning()){
				if($this->runRoom->isPlayer($player)){
					if($event->getBlock()->getPosition()->equals($this->runRoom->getEnd())){
						$this->runRoom->end($player);
					}
				}
			}
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if($sender instanceof Player){
			if(!$sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
				$sender->sendForm(new RunForm());
			}else{
				switch($args[0] ?? "x"){
					case "생성":
						if(trim($args[1] ?? "") !== ""){
							$this->mode[$sender->getName()] = [
								"name" => $args[1],
								"mode" => 1
							];
							OnixUtils::message($sender, $args[1] . " 달리기 게임의 스폰을 터치해주세요.");
						}else{
							OnixUtils::message($sender, "/달리기 생성 [이름] - 달리기 게임 맵을 생성합니다.");
						}
						break;
					case "제거":
						if(trim($args[1] ?? "") !== ""){
							if(isset($this->db["rooms"][$args[1]])){
								unset($this->db["rooms"][$args[1]]);
								OnixUtils::message($sender, "제거하였습니다.");
							}else{
								OnixUtils::message($sender, "해당 이름의 맵이 존재하지 않습니다.");
							}
						}else{
							OnixUtils::message($sender, "/달리기 제거 [이름] - 달리기 게임 맵을 제거합니다.");
						}
						break;
					case "입장":
						$sender->sendForm(new RunForm());
						break;
					case "목록":
						$arr = [];
						foreach($this->db["rooms"] as $name => $_){
							$arr[] = $name;
						}

						OnixUtils::message($sender, "달리기 게임 목록: " . implode(", ", $arr));
						break;
					default:
						OnixUtils::message($sender, "/달리기 생성 [이름] - 달리기 게임 맵을 생성합니다.");
						OnixUtils::message($sender, "/달리기 제거 [이름] - 달리기 게임 맵을 제거합니다.");
						OnixUtils::message($sender, "/달리기 입장 - 달리기 게임에 입장합니다.");
						OnixUtils::message($sender, "/달리기 목록 - 달리기 게임 맵의 목록을 봅니다.");
				}
			}
		}
		return true;
	}

	public function handlePlayerQuit(PlayerQuitEvent $event){
		$player = $event->getPlayer();

		if($this->runRoom instanceof Run){
			if($this->runRoom->isPlayer($player)){
				$this->runRoom->removePlayer($player);
			}
		}
	}

	public function handlePlayerCommandPreprocess(PlayerCommandPreprocessEvent $event){
		$player = $event->getPlayer();

		if(substr($event->getMessage(), 0, 1) === "/"){
			if($this->runRoom instanceof Run){
				if($this->runRoom->isPlayer($player)){
					$event->cancel();
					OnixUtils::message($player, "달리기 게임 도중에는 명령어를 사용할 수 없습니다.");
				}
			}
		}
	}

	public function handleUIOpen(UIOpenEvent $event){
		$player = $event->getPlayer();

		if($this->runRoom instanceof Run){
			if($this->runRoom->isPlayer($player)){
				$event->cancel();
				OnixUtils::message($player, "달리기 게임 도중에는 시계 사용이 불가능 합니다.");
			}
		}
	}
}