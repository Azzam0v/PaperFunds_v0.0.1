<?php

namespace Azzam\Cheque;


use jojoe77777\FormAPI\CustomForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\utils\Config;
use Terpz710\EconomyPE\Money;

class Main extends PluginBase implements Listener
{
    private $cooldown = [];
    public $valeur_max;
    public $valeur_min;
    public $delai;
    public $config;

    public function OnEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveResource('config.yml');
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $this->valeur_max = $this->config->get("Valeur_max", 100000);
        $this->delai = $this->config->get("Delai", 30);
        $this->valeur_min = $this->config->get("Valeur_min", 1000);
    }

    public function Interact(PlayerItemUseEvent $event){
        $item = $event->getItem();
        $player = $event->getPlayer();
        $itemId = $this->config->get("Item_ID", "paper");
        if($item === StringToItemParser::getInstance()->parse($itemId) ?? LegacyStringToItemParser::getInstance()->parse($itemId)) {
            if ($item->hasCustomName()){
                if (!isset($this->cooldown[$player->getName()]))
                {
                    $this->cooldown[$player->getName()] = time() + $this->delai; //pour éviter tout usebug avec un bug de connexion
                    $lore = $item->getLore();
                    if(isset($lore[0])){
                        if(intval($lore[0])){
                            $p = intval($lore[0]);

                            $player->sendMessage("§9>> §fVous venez d'utiliser un chèque de §9$p$ §f!");

                            Money::getInstance()->addMoney($player, intval($lore[0]));

                            $index = $player->getInventory()->getHeldItemIndex();
                            $item = $player->getInventory()->getItem($index);
                            $item = $item->setCount($item->getCount() - 1);
                            $player->getInventory()->setItemInHand($item);
                        }
                    }
                }else{
                    if (time() < $this->cooldown[$player->getName()])
                    {
                        $temps = $this->cooldown[$player->getName()] - time();
                        $player->sendTip("§9>> §fVous ne pourrez utiliser un nouveau chèque que dans §9$temps §fsecondes !");
                    }else{
                        unset($this->cooldown[$player->getName()]);

                    }
                }
            }
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if ($sender instanceof Player) {
            if ($command->getName() === "cheque") {
                if (!$args) {
                    $this->ChequeForm($sender);
                    return true;
                }
                if(isset($args[0])){
                    if (is_numeric($args[0])) {
                        $this->onUseCheque($args, $sender);
                    }else{
                        $sender->sendMessage("Usage : /cheque <prix>");
                    }
                }else{
                    $sender->sendMessage("Usage : /cheque <prix>");
                }
            }
        }
        return true;
    }

    public function ChequeForm(Player $player)
    {
        $form = new CustomForm(function (Player $player, array $data = null) {
            $re = $data;
            if ($re === null)
            {
                return true;
            }
            $args[0] = $data[1];
            if (is_numeric($data[1])){
                $this->onUseCheque($args, $player);
            }elseif (isset($data[1])){
                $player->sendMessage("Usage : /cheque <prix>");
            }else{
                $player->sendMessage("§9>> §fVous devez mettre uniquement des chiffres !");
            }
        });
        $form->setTitle($this->config->get("Titre", "Chèque"));
        $form->addLabel("§9>> §fBienvenue sur l'interface du §9Chéquier§f ! Il vous permettra de créer des chèques qui pourront vous servir.");
        $form->addInput("§9>> §fVous devez mettre uniquement des chiffres !", "Entrez le montant du chèque");

        $form->sendToPlayer($player);
        return $form;
    }

    public function onUseCheque($args, $sender){
        $itemId = $this->config->get("Item_ID", "paper");
        $item = StringToItemParser::getInstance()->parse($itemId)->setCount(1) ?? LegacyStringToItemParser::getInstance()->parse($itemId)->setCount(1);
        if ($args[0] >= $this->valeur_min){
            if ($args[0] <= $this->valeur_max){
                $_price = round($args[0]);
                if (Money::getInstance()->getMoneyPlayer($sender) < $_price){
                    $sender->sendMessage("§cVous n'avez pas les fonds suffisants !");
                    return;
                }
                Money::getInstance()->removeMoney($sender, $args[0]);

                $item->setCustomName("§f".$this->config->get("Titre")." de §9$args[0]$");
                $item->setLore([$args[0]]);
                $sender->getInventory()->addItem($item);
                $sender->sendMessage("§9>> §fVous venez de créer un chèque de §9". $args[0] ."$ §f!");
            }else{
                $sender->sendMessage("§9>> §fLa somme maximal est de §9".$this->valeur_max." §f!");
            }
        }else{
            $sender->sendMessage("§9>> §fLa somme minimal est de §9".$this->valeur_min." §f!");
        }
    }



}