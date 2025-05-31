<?php
class mcservers{
    //Server information
    public static function serverTypeInfo(string $type):array{
        $info = array(
            "allowMods" => false,
            "modsLimit" => 0,
            "modsFolder" => "mods",

            "allowPlugins" => false,
            "pluginsLimit" => 0,
            "pluginsFolder" => "plugins",

            "allowDatapacks" => true,
            "datapacksLimit" => 0,
            "datapacksFolder" => "%worldname%/datapacks",

            "allowResourcepacks" => true,
            "resourcepacksLimit" => 1,
            "resourcepacksFolder" => "{url}",

            "hasPropertiesFile" => true,
            "hasEula" => true,
            
            "hasJarToRun" => true,
            "customRunFile" => false,
            "usesInstaller" => false,

            "customSettings" => false
        );

        if($type === "forge"){
            $info["allowMods"] = true;
            $info["usesInstaller"] = true;
        }
        elseif($type === "paper" || $type === "purpur"){
            $info["allowPlugins"] = true;
        }

        return $info;
    }
    public static function validateId($id, bool $ignoreDirectory):bool{
        $valid = false;
        if(strlen($id) === 3 && preg_match("/^[0-9]+$/",$id) === 1){
            if($ignoreDirectory){
                $valid = true;
            }
            else{
                $dir = settings::read("serversPath") . "\\" . $id;
                if(is_dir($dir)){
                    if(settings::isset("servers/" . $id)){
                        if(is_file($dir . '\\run.bat')){
                            $valid = true;
                        }
                    }
                }
            }
        }
        return $valid;
    }
    public static function parseServerData(array $serverData):array{
        $latestVersion = minecraft_releases_api::getLatest();
        if(!is_string($latestVersion)){
            $latestVersion = "1.20.4";
        }
        $newServerData = array(
            'name'    => 'Server - ' . date("Y-m-d H:i:s"),

            'version' => array(
                'type'            => 'vanilla',
                'version'         => $latestVersion,
                'special_version' => ''
            ),

            'run'     => array(
                'max_ram_mb' => 4096,
                'nogui'      => false
            ),

            'stop_command' => 'stop'
        );

        $newServerData = self::appendData($newServerData,$serverData);

        if($newServerData['run']['max_ram_mb'] < 128 && $newServerData['run']['max_ram_mb'] !== 0){
            $newServerData['run']['max_ram_mb'] = 128;
        }

        $newServerData["capabilities"] = self::serverTypeInfo($newServerData['version']['type']);

        return $newServerData;
    }
    public static function serverHasRcon($id):bool{
        if(self::validateId($id,false)){
            $serverInfo = self::serverInfo($id);
            if($serverInfo['capabilities']['hasPropertiesFile']){
                $properties = self::parseServerPropertiesFile($id);
                if($properties['enable-rcon'] == "true"){
                    return true;
                }
            }
        }
        return false;
    }
    public static function serverInfo($id):mixed{
        if(self::validateId($id,false)){
            return settings::read('servers/' . $id);
        }
        return false;
    }
    //Server properties file
    public static function serverPropertiesFileInfo():array{
        //Information from https://minecraft.wiki/w/Server.properties
        return array(
            "accepts-transfers" => array(
                "values" => array("false","true"),
                "default" => "false",
                "description" => "Allows servers to accept incoming transfers via a transfer packet."
            ),
            "allow-flight" => array(
                "values" => array("false","true"),
                "default" => "false",
                "description" => "Allows users to use flight on the server while in Survival mode, if they have a mod that provides flight installed."
            ),
            "allow-nether" => array(
                "values" => array("false","true"),
                "default" => "true",
                "description" => "Allows players to travel to the Nether."
            ),
            "broadcast-console-to-ops" => array(
                "values" => array("false","true"),
                "default" => "true",
                "description" => "Send console command outputs to all online operators."
            ),
            "broadcast-rcon-to-ops" => array(
                "values" => array("false","true"),
                "default" => "true",
                "description" => "Send rcon console command outputs to all online operators."
            ),
            "difficulty" => array(
                "values" => array("peaceful","easy","normal","hard"),
                "default" => "easy",
                "description" => "Defines the difficulty (such as damage dealt by mobs and the way hunger and poison affects players) of the server."
            ),
            "enable-command-block" => array(
                "values" => array("false","true"),
                "default" => "false",
                "description" => "Enables command blocks."
            ),
            "enable-jmx-monitoring" => array(
                "values" => array("false","true"),
                "default" => "false",
                "description" => "Exposes an MBean with the Object name (net.minecraft.server:type=Server) and two attributes (averageTickTime) and (tickTimes) exposing the tick times in milliseconds."
            ),
            "enable-rcon" => array(
                "values" => array("false","true"),
                "default" => "false",
                "description" => "Enables remote access to the server console. This is needed for PHP-CLI to communicate with the server."
            ),
            "enable-status" => array(
                "values" => array("false","true"),
                "default" => "true",
                "description" => "Makes the server appear as online in the servers list."
            ),
            "enable-query" => array(
                "values" => array("false","true"),
                "default" => "false",
                "description" => "Enables GameSpy4 protocol server listener. Used to get information about server."
            ),
            "enforce-secure-profile" => array(
                "values" => array("false","true"),
                "default" => "true",
                "description" => "If set to true, players without a Mojang-signed public key cannot connect to the server."
            ),
            "enforce-whitelist" => array(
                "values" => array("false","true"),
                "default" => "false",
                "description" => "Enforces the whitelist on the server. When this option is enabled, users who are not present on the whitelist (if it's enabled) get kicked from the server after the server reloads the whitelist file."
            ),
            "entity-broadcast-range-percentage" => array(
                "values" => array("10","25","50","75","100","150","200","250","300","400","500","750","1000"),
                "default" => "100",
                "description" => "Controls how close entities need to be before being sent to clients. Higher values means they'll be rendered from farther away, potentially causing more lag. This is expressed the percentage of the default value. For example, setting to 50 makes it half as usual. This mimics the function on the client video settings (not unlike Render Distance, which the client can customize so long as it's under the server's setting)."
            ),
            "force-gamemode" => array(
                "values" => array("false","true"),
                "default" => "false",
                "description" => "Force players to join in the default game mode."
            ),
            "function-permission-level" => array(
                "values" => array("1","2","3","4"),
                "default" => "2",
                "description" => "Sets the default permission level for functions."
            ),
            "gamemode" => array(
                "values" => array("survival","creative","adventure","spectator"),
                "default" => "survival",
                "description" => "Defines the mode of gameplay."
            ),
            "generate-structures" => array(
                "values" => array("false","true"),
                "default" => "true",
                "description" => "Defines whether structures (such as villages) can be generated. Dungeons still generate if this is set to false."
            ),
            "generator-settings" => array(
                "values" => array("string"),
                "default" => "{}",
                "description" => "The settings used to customize world generation. Follow its format and write the corresponding JSON string. Remember to escape all (:) with (\:). This only applies to generation when the level-type is minecraft:flat"
            ),
            "hardcore" => array(
                "values" => array("false","true"),
                "default" => "false",
                "description" => "If set to true, server difficulty is ignored and set to hard and players are set to spectator mode if they die."
            ),
            "hide-online-players" => array(
                "values" => array("false","true"),
                "default" => "false",
                "description" => "If set to true, a player list is not sent on status requests."
            ),
            "initial-disabled-packs" => array(
                "values" => array("string"),
                "default" => "",
                "description" => "Comma-separated list of datapacks to not be auto-enabled on world creation.",
            ),
            "initial-enabled-packs" => array(
                "values" => array("string"),
                "default" => "vanilla",
                "description" => "Comma-separated list of datapacks to be enabled during world creation. Feature packs need to be explicitly enabled."
            ),
            "level-name" => array(
                "values" => array("string"),
                "default" => "world",
                "description" => "The 'level-name' value is used as the world name and its folder name. The player may also copy their saved game folder here, and change the name to the same as that folder's to load it instead. Characters such as ' (apostrophe) may need to be escaped by adding a backslash before them."
            ),
            "level-seed" => array(
                "values" => array("string"),
                "default" => "",
                "description" => "Sets a world seed for the player's world, as in Singleplayer. The world generates with a random seed if left blank. Some examples are: minecraft, 404, 1a2b3c."
            ),
            "level-type" => array(
                "values" => array("minecraft\:normal","minecraft\:flat","minecraft\:large_biomes","minecraft\:amplified","minecraft\:single_biome_surface"),
                "default" => "minecraft\:normal",
                "description" => "Determines the world preset that is generated.\nEscaping ':' is required when using a world preset ID, and the vanilla world preset ID's namespace (minecraft:) can be omitted.\n\nminecraft:normal - Standard world with hills, valleys, water, etc.\nminecraft:flat - A flat world with no features, can be modified with generator-settings.\nminecraft:large_biomes - Same as default but all biomes are larger.\nminecraft:amplified - Same as default but world-generation height limit is increased.\nminecraft:single_biome_surface - A buffet world which the entire overworld consists of one biome, can be modified with generator-settings.\nbuffet - Only for 1.15 or before. Same as default unless generator-settings is set.\ndefault_1_1 - Only for 1.15 or before. Same as default, but counted as a different world type.\ncustomized - Only for 1.15 or before. After 1.13, this value is no different than default, but in 1.12 and before, it could be used to create a completely custom world."
            ),
            "log-ips" => array(
                "values" => array("false","true"),
                "default" => "true",
                "description" => "If set to false client IP addresses are not shown in log messages printed to the server console or the log file."
            ),
            "max-chained-neighbor-updates" => array(
                "values" => array("integer"),
                "default" => 1000000,
                "description" => "Limiting the amount of consecutive neighbor updates before skipping additional ones. Negative values remove the limit."
            ),
            "max-players" => array(
                "values" => array("integer"),
                "int_max" => 2147483647,
                "int_min" => 0,
                "default" => "20",
                "description" => "The maximum number of players that can play on the server at the same time. Note that more players on the server consume more resources. Note also, op player connections are not supposed to count against the max players, but ops currently cannot join a full server. However, this can be changed by going to the file called ops.json in the player's server directory, opening it, finding the op that the player wants to change, and changing the setting called bypassesPlayerLimit to true (the default is false). This means that that op does not have to wait for a player to leave in order to join. Extremely large values for this field result in the client-side user list being broken."
            ),
            "max-tick-time" => array(
                "values" => array("integer"),
                "int_max" => 9223372036854775807,
                "int_min" => -1,
                "default" => "60000",
                "description" => "The maximum number of milliseconds a single tick may take before the server watchdog stops the server with the message, A single server tick took 60.00 seconds (should be max 0.05); Considering it to be crashed, server will forcibly shutdown. Once this criterion is met, it calls System.exit(1).\n-1 - disable watchdog entirely (this disable option was added in 14w32a)"
            ),
            "max-world-size" => array(
                "values" => array("integer"),
                "int_max" => 29999984,
                "int_min" => 1,
                "default" => 29999984,
                "description" => "This sets the maximum possible size in blocks, expressed as a radius, that the world border can obtain. Setting the world border bigger causes the commands to complete successfully but the actual border does not move past this block limit. Setting the max-world-size higher than the default doesn't appear to do anything.\nExamples:\n\nSetting max-world-size to 1000 allows the player to have a 2000×2000 world border.\nSetting max-world-size to 4000 gives the player an 8000×8000 world border."
            ),
            "motd" => array(
                "values" => array("string"),
                "default" => "A Minecraft Server",
                "description" => "This is the message that is displayed in the server list of the client, below the name.\nThe MOTD supports color and formatting codes.\nThe MOTD supports special characters, such as '♥'. However, such characters must be converted to escaped Unicode form.\nIf the MOTD is over 59 characters, the server list may report a communication error."
            ),
            "network-compression-threshold" => array(
                "values" => array("integer"),
                "default" => 256,
                "description" => "By default it allows packets that are n-1 bytes big to go normally, but a packet of n bytes or more gets compressed down. So, a lower number means more compression but compressing small amounts of bytes might actually end up with a larger result than what went in.\n\n-1 - disable compression entirely\n0 - compress everything\n\nNote: The Ethernet spec requires that packets less than 64 bytes become padded to 64 bytes. Thus, setting a value lower than 64 may not be beneficial. It is also not recommended to exceed the MTU, typically 1500 bytes."
            ),
            "online-mode" => array(
                "values" => array("false","true"),
                "default" => "true",
                "description" => "Server checks connecting players against Minecraft account database. Set this to false only if the player's server is not connected to the Internet. Hackers with fake accounts can connect if this is set to false! If minecraft.net is down or inaccessible, no players can connect if this is set to true. Setting this variable to off purposely is called 'cracking' a server, and servers that are present with online mode off are called 'cracked' servers, allowing players with unlicensed copies of Minecraft to join.\n\ntrue - Enabled. The server assumes it has an Internet connection and checks every connecting player.\nfalse - Disabled. The server does not attempt to check connecting players."
            ),
            "op-permission-level" => array(
                "values" => array("0","1","2","3","4"),
                "default" => "4",
                "description" => "Sets the default permission level for ops when using /op."
            ),
            "player-idle-timeout" => array(
                "values" => array("integer"),
                "default" => 0,
                "description" => "If non-zero, players are kicked from the server if they are idle for more than that many minutes."
            ),
            "prevent-proxy-connections" => array(
                "values" => array("false","true"),
                "default" => "false",
                "description" => "If the ISP/AS sent from the server is different from the one from Mojang Studios' authentication server, the player is kicked."
            ),
            "pvp" => array(
                "values" => array("false","true"),
                "default" => "true",
                "description" => "Enable PvP on the server. Players shooting themselves with arrows receive damage only if PvP is enabled.\n\ntrue - Players can kill each other.\nfalse - Players cannot kill other players (also known as Player versus Environment (PvE)).\n\nNote: Indirect damage sources spawned by players (such as lava, fire, TNT and to some extent water, sand and gravel) still deal damage to other players."
            ),
            "query.port" => array(
                "values" => array("integer"),
                "int_max" => 65534,
                "int_min" => 1,
                "default" => 25565,
                "description" => "Sets the port for the query server (see enable-query). Please do not change this"
            ),
            "rate-limit" => array(
                "values" => array("integer"),
                "default" => 0,
                "description" => "Sets the maximum amount of packets a user can send before getting kicked. Setting to 0 disables this feature."
            ),
            "rcon.password" => array(
                "values" => array("string"),
                "default" => "",
                "description" => "Sets the password for RCON: a remote console protocol that can allow other applications to connect and interact with a Minecraft server over the internet. Please do not change this."
            ),
            "rcon.port" => array(
                "values" => array("integer"),
                "int_max" => 65534,
                "int_min" => 1,
                "default" => 25575,
                "description" => "Sets the RCON network port. Please do not change this"
            ),
            "region-file-compression" => array(
                "values" => array("deflate","lz4","none"),
                "default" => "deflate",
                "description" => "Changes the algorithm for the compression of chunks in regions."
            ),
            "resource-pack" => array(
                "values" => array("string"),
                "default" => "",
                "description" => "Optional URI to a resource pack. The player may choose to use it.\nNote that (in some versions before 1.15.2), the ':' and '=' characters need to be escaped with a backslash (\), e.g. http\://somedomain.com/somepack.zip?someparam\=somevalue\nThe resource pack may not have a larger file size than 250 MiB (Before 1.18: 100 MiB (≈ 100.8 MB)) (Before 1.15: 50 MiB (≈ 50.4 MB)). Note that download success or failure is logged by the client, and not by the server."
            ),
            "resource-pack-id" => array(
                "values" => array("string"),
                "default" => "",
                "description" => "Optional UUID for the resource pack set by resource-pack to identify the pack with clients."
            ),
            "resource-pack-prompt" => array(
                "values" => array("string"),
                "default" => "",
                "description" => "Optional, adds a custom message to be shown on resource pack prompt when require-resource-pack is used.\nExpects chat component syntax, can contain multiple lines."
            ),
            "resource-pack-sha1" => array(
                "values" => array("string"),
                "default" => "",
                "description" => "Optional SHA-1 digest of the resource pack, in lowercase hexadecimal. It is recommended to specify this, because it is used to verify the integrity of the resource pack.\nNote: If the resource pack is any different, a yellow message 'Invalid sha1 for resource-pack-sha1' appears in the console when the server starts. Due to the nature of hash functions, errors have a tiny probability of occurring, so this consequence has no effect."
            ),
            "require-resource-pack" => array(
                "values" => array("false","true"),
                "default" => "false",
                "description" => "When this option is enabled (set to true), players are prompted for a response and get disconnected if they decline the required pack."
            ),
            "server-ip" => array(
                "values" => array("string"),
                "default" => "",
                "description" => "The player should set this if they want the server to bind to a particular IP. It is strongly recommended that the player leaves server-ip blank.\nSet to blank, or the IP the player want their server to run (listen) on."
            ),
            "server-port" => array(
                "values" => array("integer"),
                "int_max" => 65534,
                "int_min" => 1,
                "default" => 25565,
                "description" => "Changes the port the server is hosting (listening) on. This port must be forwarded if the server is hosted in a network using NAT (if the player has a home router/firewall)."
            ),
            "simulation-distance" => array(
                "values" => array("integer"),
                "int_max" => 32,
                "int_min" => 3,
                "default" => 10,
                "description" => "Sets the maximum distance from players that living entities may be located in order to be updated by the server, measured in chunks in each direction of the player (radius, not diameter). If entities are outside of this radius, then they are not ticked by the server and they are not visible to players.\n10 is the default/recommended. If the player has major lag, this value is recommended to be reduced."
            ),
            "snooper-enabled" => array(
                "values" => array("false","true"),
                "default" => "true",
                "description" => "Sets whether the server sends snoop data regularly to http://snoop.minecraft.net.\nfalse - disable snooping.\ntrue - enable snooping."
            ),
            "spawn-animals" => array(
                "values" => array("false","true"),
                "default" => "true",
                "description" => "Determines if animals can spawn.\ntrue - Animals spawn as normal.\nfalse - Animals immediately vanish.\nIf the player has major lag, it is recommended to turn this off/set to false."
            ),
            "spawn-monsters" => array(
                "values" => array("false","true"),
                "default" => "true",
                "description" => "	Determines if monsters can spawn.\ntrue - Enabled. Monsters appear at night and in the dark.\nfalse - Disabled. No monsters.\nThis setting has no effect if difficulty = 0 (peaceful). If difficulty is not = 0, a monster can still spawn from a monster spawner.\nIf the player has major lag, it is recommended to turn this off/set to false."
            ),
            "spawn-npcs" => array(
                "values" => array("false","true"),
                "default" => "true",
                "description" => "Determines whether villagers can spawn.\ntrue - Enabled. Villagers spawn.\nfalse - Disabled. No villagers."
            ),
            "spawn-protection" => array(
                "values" => array("integer"),
                "default" => 16,
                "description" => "Determines the side length of the square spawn protection area as 2x+1. Setting this to 0 disables the spawn protection. A value of 1 protects a 3×3 square centered on the spawn point. 2 protects 5×5, 3 protects 7×7, etc. This option is not generated on the first server start and appears when the first player joins. If there are no ops set on the server, the spawn protection is disabled automatically as well."
            ),
            "sync-chunk-writes" => array(
                "values" => array("false","true"),
                "default" => "true",
                "description" => "Enables synchronous chunk writes."
            ),
            "text-filtering-config" => array(
                "values" => array("string"),
                "default" => "",
                "description" => ""
            ),
            "use-native-transport" => array(
                "values" => array("false","true"),
                "default" => "true",
                "description" => "Linux server performance improvements: optimized packet sending/receiving on Linux\ntrue - Enabled. Enable Linux packet sending/receiving optimization\nfalse - Disabled. Disable Linux packet sending/receiving optimization"
            ),
            "view-distance" => array(
                "values" => array("integer"),
                "int_max" => 32,
                "int_min" => 3,
                "default" => 10,
                "description" => "Sets the amount of world data the server sends the client, measured in chunks in each direction of the player (radius, not diameter). It determines the server-side viewing distance.\n10 is the default/recommended. If the player has major lag, this value is recommended to be reduced."
            ),
            "white-list" => array(
                "values" => array("false","true"),
                "default" => "false",
                "description" => "Enables a whitelist on the server.\nWith a whitelist enabled, users not on the whitelist cannot connect. Intended for private servers, such as those for real-life friends or strangers carefully selected via an application process, for example.\nfalse - No white list is used.\ntrue - The file whitelist.json is used to generate the white list.\nNote: Ops are automatically whitelisted, and there is no need to add them to the whitelist."
            )
        );
    }
    private static function serverPropertiesString(string $server, array $serverData):string{
        $properties = "";

        if($server === "001"){
            $properties .= "server-port=25565";
        }
        else{
            $properties .= "server-port=21" . $server;
        }
        $properties .= "\nquery.port=22" . $server;
        $properties .= "\nrcon.port=23" . $server;
        $properties .= "\nmotd=Server " . $server;
        $properties .= "\ndifficulty=normal";
        $properties .= "\nview-distance=16";

        $properties .= "\nenable-rcon=";
        if($serverData['rcon']){
            $properties .= "true";
        }
        else{
            $properties .= "false";
        }

        $properties .= "\nrcon.password=" . preg_replace("/[^A-Za-z0-9]/",'_',base64_encode("abcdefg_" . $server . "_rcon"));

        return $properties;
    }
    public static function parseServerPropertiesFile($id):array{
        $return = array();
        if(self::validateId($id,false)){
            $info = self::serverInfo($id);
            if($info['capabilities']['hasPropertiesFile'] === true){
                $propertiesFile = self::serverDir() . "\\" . $id . "\\server.properties";
                $propertiesString = txtrw::readtxt($propertiesFile);
                if(!empty($propertiesString)){
                    $properties = array();
                    foreach(explode("\n",$propertiesString) as $property){
                        $properties[] = trim($property);
                    }
                    if(!empty($properties)){
                        foreach($properties as $property){
                            if(!empty($property)){
                                if(substr($property,0,1) !== "#"){
                                    $eqPos = strpos($property,"=",1);
                                    if(is_int($eqPos)){
                                       $return[substr($property,0,$eqPos)] = substr($property,$eqPos+1);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $return;
    }
    public static function modifyServerPropertiesFile($id,array $data):bool{
        if(self::validateId($id,false)){
            $info = self::serverInfo($id);
            if($info['capabilities']['hasPropertiesFile'] === true){
                $propertiesFile = self::serverDir() . "\\" . $id . "\\server.properties";
                if(is_file($propertiesFile)){
                    $properties = file($propertiesFile);
                    if(is_array($properties)){
                        $expectedProperties = self::serverPropertiesFileInfo();
                        foreach($data as $inputName => $inputValue){
                            if(isset($expectedProperties[$inputName])){
                                foreach($properties as $lineNumber => $property){
                                    $eqPos = strpos($property,"=",1);
                                    if(is_int($eqPos)){
                                        $propertyName = substr($property,0,$eqPos);
                                        if($propertyName === $inputName){
                                            $properties[$lineNumber] = $inputName . "=" . $inputValue . "\n";
                                        }
                                    }
                                }
                            }
                        }
                        if(file_put_contents($propertiesFile,$properties) !== false){
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }
    //Server creation
    public static function createServer(array $serverData = array()):bool{
        $serverData = self::parseServerData($serverData);
        
        $serversPath = settings::read('serversPath');
        $server = 1;
        while($server < 999){
            $server = str_pad($server,3,"0",STR_PAD_LEFT);
            if(is_dir($serversPath . "\\" . $server) || settings::isset("servers/" . $server)){
                $server ++;
            }
            else{
                break;
            }
        }

        if(is_int($server)){
            mklog('warning','It is suspected that the maximum number of servers has been reached (999)');
            return false;
        }

        mklog('general','Creating server with id ' . $server,false);

        if($server === "001"){
            $serverPort = "25565";
        }
        else{
            $serverPort = "21" . $server;
        }

        $serverDir = $serversPath . "\\" . $server;
        
        files::mkFolder($serverDir);

        $serverData['rcon'] = false;

        $return = false;

        if($serverData['version']['type'] === "paper"){
            $jarPath = papermc_api_v2::filePath("paper",$serverData['version']['version'],$serverData['version']['special_version'],true);
            $serverData['rcon'] = true;
            if(is_string($jarPath)){
                $jarName = files::getFileName($jarPath);
                files::copyFile($jarPath,$serverDir . "\\" . $jarName);
    
                file_put_contents($serverDir . "\\run.bat", self::serverRunFileString($server, $serverData, $jarName));
    
                file_put_contents($serverDir . "\\server.properties", self::serverPropertiesString($server, $serverData));
    
                $return = true;
            }
        }
        elseif($serverData['version']['type'] === "purpur"){
            $jarPath = purpur_downloads_api_v2::filePath("purpur",$serverData['version']['version'],$serverData['version']['special_version'],true);
            $serverData['rcon'] = true;
            if(is_string($jarPath)){
                $jarName = files::getFileName($jarPath);
                files::copyFile($jarPath,$serverDir . "\\" . $jarName);
    
                file_put_contents($serverDir . "\\run.bat", self::serverRunFileString($server, $serverData, $jarName));
    
                file_put_contents($serverDir . "\\server.properties", self::serverPropertiesString($server, $serverData));
    
                $return = true;
            }
        }
        elseif($serverData['version']['type'] === "waterfall" ){
            $jarPath = papermc_api_v2::filePath("waterfall",$serverData['version']['version'],$serverData['version']['special_version'],true);
            if(is_string($jarPath)){
                $jarName = files::getFileName($jarPath);
                files::copyFile($jarPath,$serverDir . "\\" . $jarName);

                file_put_contents($serverDir . "\\run.bat", self::serverRunFileString($server, $serverData, $jarName));

                $configYML = "";
                $configYML .= "listeners:\n";
                $configYML .= "- query_port: " . $serverPort . "\n";
                $configYML .= "  host: 0.0.0.0:" . $serverPort . "\n";
                file_put_contents($serverDir . "\\config.yml",$configYML);

                $return = true;
            }
        }
        elseif($serverData['version']['type'] === "velocity"){
            $jarPath = papermc_api_v2::filePath("velocity",$serverData['version']['version'],$serverData['version']['special_version'],true);
            if(is_string($jarPath)){
                $jarName = files::getFileName($jarPath);
                files::copyFile($jarPath,$serverDir . "\\" . $jarName);

                file_put_contents($serverDir . "\\run.bat", self::serverRunFileString($server, $serverData, $jarName));

                file_put_contents($serverDir . "\\velocity.toml",'bind = "0.0.0.0:' . $serverPort . '"');

                $return = true;
            }
        }
        elseif($serverData['version']['type'] === "forge"){
            $serverData['rcon'] = true;
            if(forge_installer::installForge($serverData['version']['version'],$serverData['version']['special_version'],$serverDir)){

                $runFileLines = file($serverDir . "\\run.bat");
                $runFileJarLine = 0;
                foreach($runFileLines as $runFileLine){
                    if(substr(trim($runFileLine),0,4) === "java"){
                        break;
                    }
                    $runFileJarLine++;
                }

                $runContents = "";
                $runContents .= "title MCServer_" . $server . "\n";
                $runContents .= trim($runFileLines[$runFileJarLine]);
                $runContents .= "\nexit";
                txtrw::mktxt($serverDir . "\\run.bat",$runContents,true);

                $jvmArgs = "";
                if($serverData['run']['max_ram_mb'] > 127){
                    $jvmArgs .= "-Xmx" . $serverData['run']['max_ram_mb'] . "M ";
                }
                txtrw::mktxt($serverDir . "\\user_jvm_args.txt",$jvmArgs,true);

                txtrw::mktxt($serverDir . "\\server.properties",self::serverPropertiesString($server,$serverData),true);

                $return = true;
            }
        }
        else{
            $jarPath = minecraft_releases_api::filePath($serverData['version']['version'],false,true);
            $serverData['rcon'] = true;
            if(is_string($jarPath)){
                $jarName = files::getFileName($jarPath);
                files::copyFile($jarPath,$serverDir . "\\" . $jarName);
    
                file_put_contents($serverDir . "\\run.bat", self::serverRunFileString($server, $serverData, $jarName));
    
                file_put_contents($serverDir . "\\server.properties", self::serverPropertiesString($server, $serverData));
    
                $return = true;
            }
        }

        if($return === true){
            settings::set('servers/' . $server,$serverData);
            txtrw::mktxt($serverDir . "\\eula.txt","eula=true",true);
            self::getServerStats($server);
            return true;
        }
        return false;
    }
    private static function serverRunFileString(string $server, array $serverData, string $jarName):string{
        $runContents = "java ";
        if($serverData['run']['max_ram_mb'] !== 0){
            if($serverData['run']['max_ram_mb'] > 127){
                $runContents .= "-Xms" . round($serverData['run']['max_ram_mb']/2) . "M ";
                $runContents .= "-Xmx" . $serverData['run']['max_ram_mb'] . "M ";
            }
        }
        $runContents .= "-jar " . $jarName . " ";
        if($serverData['run']['nogui'] === true){
            $runContents .= "nogui";
        }
        return $runContents;
    }
    //Data processing
    private static function appendData($cleanArray,$newCrapArray):array{
        foreach($cleanArray as $cleanName => $cleanValue){
            if($cleanName === "custom_data"){
                $cleanArray['custom_data'] = $cleanValue;
            }
            else{
                if(isset($newCrapArray[$cleanName])){
                    $cleanValueDatatype = gettype($cleanValue);
                    $newCrapArrayItemDatatype = gettype($newCrapArray[$cleanName]);
                    if($cleanValueDatatype === "array"){
                        $cleanArray[$cleanName] = self::appendData($cleanValue,$newCrapArray[$cleanName]);
                    }
                    else{
                        if($cleanValueDatatype === $newCrapArrayItemDatatype){
                            $cleanArray[$cleanName] = $newCrapArray[$cleanName];
                        }
                    }
                }
            }
        }
        return $cleanArray;
    }
    public static function datatype(string $value):string|float|int|bool{
        $dotpos = strpos($value,":");
        if($dotpos === false){
            $datatype = "string";
        }
        else{
            $datatype = substr($value,0,$dotpos);
            $value = substr($value,$dotpos+1);
        }

        if($datatype === "string"){
            return $value;
        }
        elseif($datatype === "float"){
            return floatval($value);
        }
        elseif($datatype === "int"){
            return intval($value);
        }
        elseif($datatype === "bool"){
            if(strtolower($value) === "true"){
                return true;
            }
            else{
                return false;
            }
        }
        else{
            return false;
        }
    }
    //CLI functions
    public static function init():void{
        $defaultSettings = array(
            "serversPath"            => "mcservers",
            "libraryPath"            => "mcservers\\library",
            "mainServers"            => array(),
            "backupsPath"            => "mcserverbackups",
            "backupShutdownWaitTime" => 30
        );
        foreach($defaultSettings as $settingName => $settingValue){
            if(!settings::isset($settingName)){
                settings::set($settingName,$settingValue);
            }
        }
        extensions::ensure('sockets');
        
        mklog('general','Checking Java version',false);
        if(!cmd::run('java --version')){
            echo 'Java SE Environment is not installed, would you like to install JDK 21 LTS?' . "\n";
            if(user_input::yesNo()){
                echo 'Downloading Java' . "\n";
                if(downloader::downloadFile('https://download.oracle.com/java/21/latest/jdk-21_windows-x64_bin.exe','temp\\latestJDK.exe')){
                    echo "Running installer\n";
                    if(!cmd::run('temp\\latestJDK.exe')){
                        echo "Failed to run the installer!\n";
                    }
                    unlink('temp\\latestJDK.exe');
                }
                else{
                    echo "Failed to download java";
                }
            }
            else{
                echo "Not installing java\n";
            }
        }

        $servers = settings::read('servers');
        if(is_array($servers)){
            foreach($servers as $serverId => $serverInfo){
                if(!isset($serverInfo['capabilities'])){
                    $serverInfo['capabilities'] = self::serverTypeInfo($serverInfo['version']['type']);
                    settings::set('servers/' . $serverId, $serverInfo, true);
                }
            }
        }
    }
    public static function listServers(array $servers,bool $checkRunning = false, bool|int $expectedRunning = 0):string{
        $showRunning = false;
        if($checkRunning || !is_int($expectedRunning)){
            $showRunning = true;
        }

        if($showRunning){
            $return = "|------------------------------------------------------------------------------|\n";
            $return .= "| Server id | Port number | Running | Type        | Name                       |\n";
            $return .= "|-----------|-------------|---------|-------------|----------------------------|\n";
        }
        else{
            $return = "|--------------------------------------------------------------------|\n";
            $return .= "| Server id | Port number | Type        | Name                       |\n";
            $return .= "|-----------|-------------|-------------|----------------------------|\n";
        }
        $tableFilled = false;
        foreach($servers as $server){
            if(self::validateId($server,false)){
                $tableFilled = true;
                $serverData = settings::read('servers/' . $server);
                $port = "21" . $server;
                if($port === "21001"){
                    $port = "25565";
                }
                $type = str_pad($serverData['version']['type'], 11, ' ', STR_PAD_RIGHT);
                if(strlen(trim($type)) > 10){
                    $type = substr($type,0,10) . ".";
                }
                $name = str_pad($serverData['name'], 26, ' ', STR_PAD_RIGHT);
                if(strlen(trim($name)) > 25){
                    $name = substr($name,0,25) . ".";
                }
                $running = "false  ";
                if(is_int($expectedRunning)){
                    if($checkRunning){
                        if(self::pingServer($server)){
                            $running = "true   ";
                        }
                    }
                }
                elseif($expectedRunning === true){
                    $running = "true   ";
                }

                if($showRunning){
                    $return .= "| " . $server . "       | " . $port . "       | " . $running . " | " . $type . " | " . $name . " |\n";
                }
                else{
                    $return .= "| " . $server . "       | " . $port . "       | " . $type . " | " . $name . " |\n";
                }
            }
        }
        if(!$tableFilled){
            if($showRunning){
                $return .= "| -         | -           | -       | -           | -                          |\n";
            }
            else{
                $return .= "| -         | -           | -           | -                          |\n";
            }
        }
        if($showRunning){
            $return .= "|------------------------------------------------------------------------------|\n";
        }
        else{
            $return .= "|--------------------------------------------------------------------|\n";
        }
        return $return;
    }
    public static function command($line):void{
        $lines = explode(" ",$line);
        if($lines[0] === "server"){
            if(isset($lines[1])){
                $servers = array();
                if($lines[1] === "all"){
                    $servers = self::allServers();
                }
                elseif($lines[1] === "main"){
                    $servers = settings::read('mainServers');
                }
                elseif($lines[1] === "on"){
                    echo "Pinging all servers, this could take a while...\n";
                    foreach(self::allServers() as $server){
                        if(self::pingServer($server)){
                            $servers[] = $server;
                        }
                    }
                    unset($server);
                }
                elseif($lines[1] === "off"){
                    echo "Pinging all servers, this could take a while...\n";
                    foreach(self::allServers() as $server){
                        if(!self::pingServer($server)){
                            $servers[] = $server;
                        }
                    }
                    unset($server);
                }
                elseif(self::validateId($lines[1],false)){
                    $servers[] = $lines[1];
                }
                else{
                    echo "Unknown server group or id!\n";
                }
    
                if($servers === array()){
                    if(isset($lines[2])){
                        echo "There are no servers that match the current criteria\n";
                    }
                }
    
                if(isset($lines[2])){
                    if($lines[2] === "list"){
                        if($servers !== array()){
                            $expectedRunning = 0;
                            if($lines[1] === "off"){
                                $expectedRunning = false;
                            }
                            elseif($lines[1] === "on"){
                                $expectedRunning = true;
                            }
                            $checkRunning = false;
                            if($lines[1] !== "on" && $lines[1] !== "off"){
                                if(isset($lines[3])){
                                    if($lines[3] === "ping"){
                                        if(count($servers) > 5){
                                            echo "The selected group has more than 5 servers, are you sure you want to ping all the servers in the group?";
                                            if(user_input::yesNo()){
                                                $checkRunning = true;
                                            }
                                        }
                                        else{
                                            $checkRunning = true;
                                        }
                                    }
                                }
                            }
    
                            echo self::listServers($servers,$checkRunning,$expectedRunning);
                            $servers = array();
                        }
                    }
                }
    
                foreach($servers as $server){
                    if(isset($lines[2])){
                        if($lines[2] === "stop"){
                            self::stop($server);
                        }
                        elseif($lines[2] === "start"){
                            if(!self::pingServer($server)){
                                self::start($server);
                            }
                            else{
                                mklog('warning','Unable to start server ' . $server . ' as its port is allready in use',false);
                            }
                        }
                        elseif($lines[2] === "backup"){
                            $backupName = time::stamp();
                            if(isset($lines[3])){
                                $lines[3] = strtolower($lines[3]);
                                if(preg_match("/^[a-z0-9]+$/", $lines[3]) === 1){
                                    $backupName = $lines[3];
                                }
                            }
                            $askForOverwrite = true;
                            if(isset($lines[4])){
                                if($lines[4] === "shutup"){
                                    $askForOverwrite = false;
                                }
                            }
                            self::backupServer($server,$backupName,$askForOverwrite);
                        }
                        elseif($lines[2] === "sendcommand"){
                            if(isset($lines[3])){
                                array_shift($lines);
                                array_shift($lines);
                                $command = implode(" ",$lines);
                                self::sendCommand($server,$command);
                            }
                        }
                        elseif($lines[2] === "delete"){
                            self::deleteServer($server);
                        }
                        else{
                            echo "Unknown action\n";
                        }
                    }
                    else{
                        echo "No action specified\n";
                    }
                }
            }
            else{
                echo "Server not specified\n";
            }
        }
        elseif($lines[0] === "create"){
            $serverData = array();
            if(isset($lines[1])){
                $serverData['version']['type'] = $lines[1];
            }
            if(isset($lines[2])){
                $serverData['version']['version'] = $lines[2];
            }
            if(isset($lines[3])){
                $serverData['version']['special_version'] = $lines[3];
            }
            self::createServer($serverData);
        }
        elseif($lines[0] === "start-companion"){
            if(isset($lines[1])){
                if(self::validateId($lines[1],false)){
                    self::companion($lines[1]);
                }
            }
        }
        else{
            echo "Command not found!\n";
        }
    }
    //Server status
    public static function getServerStats($id):array|false{
        if(self::validateId($id,false)){
            return self::sendCompanionData($id,"getStats");
        }
        return false;
    }
    public static function isOnline($id):bool{
        $result = self::getServerStats($id);
        if($result !== false){
            if($result['state'] === "online"){
                return true;
            }
        }
        return false;
    }
    public static function pingServer($id,float $timeout = 0.2):bool{
        if(self::validateId($id,false)){
            $serverInfo = self::serverInfo($id);
            if($serverInfo['capabilities']['hasPropertiesFile']){
                $properties = self::parseServerPropertiesFile($id);
                if(isset($properties['server-port'])){
                    $port = $properties['server-port'];
                }
            }
            
            if(!isset($port)){
                $port = "21" . $id;
                if($port === "21001"){
                    $port = "25565";
                }
            }
            
            return network::ping('localhost',$port,$timeout);
        }
        return false;
    }
    public static function serverStatus($id):string{
        $result = self::getServerStats($id);
        if($result !== false){
            return $result['state'];
        }
        return '';
    }
    public static function mirrorConsole($id, int $interval = 1){
        while(true){
            echo self::sendCompanionData($id,"getStats")['newoutput'];
            sleep($interval);
        }
    }
    public static function sendCompanionData($id, string $action, string $payload = ""):array|bool{
        $return = false;
        if(self::validateId($id,false)){
            $retried = false;
            retry:

            $socket = @stream_socket_client("tcp://127.0.0.1:25" . $id, $socketErrorCode, $socketErrorString, 3);
            if(!$socket){
                if($retried === false){
                    $retried = true;
                    cmd::newWindow('php\\php.exe cli.php command "mcservers start-companion ' . $id . '" no-loop true');
                    sleep(4);
                    goto retry;
                }
                return false;
            }

            $data['action'] = $action;
            $data['payload'] = $payload;
    
            $data = base64_encode(json_encode($data));
    
            if(fwrite($socket,$data) === false){
                $return = false;
                goto end;
            }
    
            $result = fread($socket,1024);
            if($result === false){
                $return = false;
                goto end;
            }
    
            $result = json_decode(base64_decode($result),true);
            if($result === null){
                $return = false;
                goto end;
            }

            $return = $result;

            end:
            fclose($socket);
            return $return;
        }
        return $return;
    }
    public static function companion($id){
        if(self::validateId($id,false)){
            //Start Socket Server
            extensions::ensure('sockets');

            exec("title PHP-CLI (MCServers): Server " . $id . " process");
            if(class_exists("cli_formatter")){
                cli_formatter::clear();
            }

            self::companion_echo("Info","Starting Socket Server");
            $socketServer = stream_socket_server("tcp://127.0.0.1:25" . $id, $socketServerErrorCode, $socketServerErrorString);

            if(!$socketServer){
                mklog('warning','Unable to start socket server: ' . $socketServerErrorString,false);
                return;
            }

            stream_set_timeout($socketServer,1);

            //Setup variables
            $process = false;
            $processPipes = array(false);
            $backupProcess = false;
            $backupProcessPipes = array(false,false);
            $break = false;
            $checkPing = false;
            $refreshServerInfo = true;
            $serverStats = array(
                "requestedState" => "",
                "state" => "stopped",
                "startable" => true,

                "pid" => "unknown",
                "isrunning" => false,
                "cpu" => 0,
                "memory" => 0,

                "newoutput" => "",
                "lastcommand" => "",

                "lastPingResult" => false,
                "lastRconPingResult" => false,
                "lastPingCheck" => 0,
                "lastStart" => 0,
                "lastStop" => 0,
            );

            //Loop
            while(true){
                //Wait for connection from external source
                $clientSocket = @stream_socket_accept($socketServer,1);
                if($clientSocket){
                    $data = fread($clientSocket,1024);
                    self::companion_echo("Info","Companion connection received");

                    $data = json_decode(base64_decode($data),true);
                    $response = false;

                    if(isset($data['action']) && isset($data['payload'])){

                        if($data["action"] === "getStats"){
                            $response = $serverStats;
                            $serverStats['newoutput'] = "";
                        }
                        elseif($data["action"] === "start"){
                            $response = true;
                            $serverStats['requestedState'] = "online";
                        }
                        elseif($data["action"] === "stop"){
                            $response = true;
                            $serverStats['requestedState'] = "stopped";
                        }
                        elseif($data["action"] === "sendCommand"){
                            $response = true;
                            $serverStats['lastcommand'] = $data['payload'];
                        }
                        elseif($data["action"] === "backup"){
                            $response = true;
                            $serverStats['requestedState'] = "backup";
                        }
                        elseif($data["action"] === "obliterate-self"){
                            $response = true;
                            $break = true;
                            $serverStats['requestedState'] = "stopped";
                        }
                        elseif($data["action"] === "kill"){
                            $response = true;
                            $serverStats['requestedState'] = "dead";
                        }
                        else{
                            $serverStats['newoutput'] .= self::companion_echo("Warning","Unknown companion action");
                        }
                    }
                    else{
                        $serverStats['newoutput'] .= self::companion_echo("Warning","Empty companion input");
                    }

                    $response = base64_encode(json_encode($response));
                    fwrite($clientSocket,$response);
                    fclose($clientSocket);
                }
                
                //CHECK FOR BREAK
                if($break){
                    $serverStats['newoutput'] .= self::companion_echo("Info","Ending");
                    mklog('general','Obliterate-self command sent to companion process for server ' . $id,false);
                    break;
                }

                //UPDATE SERVER INFO IF NEEDED
                if($refreshServerInfo){
                    $refreshServerInfo = false;
                    //Load server information
                    $serverInfo = self::serverInfo($id);
                    if(isset($serverInfo['capabilities']['hasPropertiesFile'])){
                        if($serverInfo['capabilities']['hasPropertiesFile']){
                            $serverPropertiesFile = self::parseServerPropertiesFile($id);
                        }
                    }
                }

                ////////////////////
                
                //GET PROCESS STATUS
                $processStats = false;
                if(is_resource($process)){
                    $processStats = proc_get_status($process);
                }
                if(!is_array($processStats)){
                    $processStats = array();
                    $processStats['pid'] = "unknown";
                    $processStats['running'] = false;
                }
                $serverStats['isrunning'] = $processStats['running'];

                //Backup Process
                $backupProcessStats = false;
                if(is_resource($backupProcess)){
                    $backupProcessStats = proc_get_status($backupProcess);
                }
                if(!is_array($backupProcessStats)){
                    $backupProcessStats = array();
                    $backupProcessStats['pid'] = "unknown";
                    $backupProcessStats['running'] = false;
                }
                
                //CHECK IF PROCESS NEEDS TO BE CLOSED
                if(!$processStats['running']){
                    if(is_resource($process)){
                        if($serverStats['state'] !== "stopping"){
                            $serverStats['newoutput'] .= self::companion_echo("Warning","Minecraft process Unexpectedly Ended");
                        }
                        //Close process
                        fclose($processPipes[0]);
                        $exitCode = proc_close($process);
                        $serverStats['newoutput'] .= self::companion_echo("Info","Minecraft process closed (" . $exitCode . ")");
                        $process = false;
                        $serverStats['lastStop'] = time::stamp();
                        $serverStats['pid'] = "unknown";
                        $serverStats['cpu'] = 0;
                        $serverStats['memory'] = 0;
                        $serverStats['state'] = "stopped";
                    }
                }
                
                //UPDATE PID IF NEEDED
                if($serverStats['pid'] === "unknown"){
                    if($processStats['running']){
                        $serverStats['pid'] = mcservers::getRootJavaProcess($processStats['pid']);
                    }
                }
                
                //GET PROCESS USAGES
                if($serverStats['pid'] !== "unknown"){
                    if($processStats['running']){
                        $serverStats['memory'] = system_api::getProcessMemoryUsage($serverStats['pid']);
                        $serverStats['cpu'] = system_api::getProcessCpuUsage($serverStats['pid']);
                    }
                }

                ////////////////////

                //ENSURE ONLY ONE PROCESS
                if($processStats['running'] || $serverStats['lastPingResult'] || $serverStats['lastRconPingResult']){
                    $serverStats['startable'] = false;
                }

                //START MINECRAFT SERVER IF ONLINE REQUESTED
                if($serverStats['requestedState'] === "online" && $serverStats['startable']){
                    $serverStats['requestedState'] = "";
                    //Start process
                    $process = proc_open("run.bat", [0 => ['pipe', 'r']], $processPipes, self::serverDir() . "\\" . $id);
                    if(is_resource($process)){
                        $serverStats['newoutput'] .= self::companion_echo("Info","Minecraft process started");
                        $serverStats['startable'] = false;
                        $serverStats['state'] = "starting";
                        $serverStats['lastStart'] = time::stamp();
                    }
                    else{
                        $serverStats['newoutput'] .= self::companion_echo("Error","Unable to start Minecraft Server process");
                    }
                    //Update server info
                    $refreshServerInfo = true;
                }

                if(!$backupProcessStats['running']){
                    if(is_resource($backupProcess)){
                        //Close process
                        fclose($backupProcessPipes[0]);
                        fclose($backupProcessPipes[1]);
                        $backupExitCode = proc_close($backupProcess);
                        $serverStats['newoutput'] .= self::companion_echo("Info","Backup process closed (" . $backupExitCode . ")");
                        $backupProcess = false;
                        $serverStats['state'] = "stopped";
                    }
                }

                if($serverStats['requestedState'] === "backup" && $serverStats['state'] === "stopped"){
                    $serverStats['requestedState'] = "";
                    $backupProcess = proc_open('php\\php.exe cli.php command "mcservers server ' . $id . ' backup" no-loop true', [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $backupProcessPipes);
                    if(is_resource($backupProcess)){
                        $serverStats['newoutput'] .= self::companion_echo("Info","Backup process started");
                        $serverStats['startable'] = false;
                        $serverStats['state'] = "backup";
                    }
                    else{
                        $serverStats['newoutput'] .= self::companion_echo("Error","Unable to start Backup process");
                    }
                }

                if($serverStats['requestedState'] === "dead" && is_resource($process) && $serverStats['pid'] !== "unknown"){
                    $serverStats['requestedState'] = "";
                    shell_exec('taskkill /F /T /PID '.$serverStats['pid']);
                    $serverStats['newoutput'] .= self::companion_echo("Warning","Attempt made to terminate Minecraft Server process");
                }

                //GET PING IF SERVER CHANGING STATE
                if($serverStats['state'] === "starting" || $serverStats['state'] === "stopping"){
                    $checkPing = true;
                }

                if($serverStats['state'] === "stopped"){
                    if($serverStats['lastPingResult'] || $serverStats['lastRconPingResult']){
                        $checkPing = true;
                    }
                }

                if(!$serverStats['isrunning'] && $serverStats['state'] === "stopped" && !$serverStats['lastPingResult'] && !$serverStats['lastRconPingResult']){
                    $serverStats['startable'] = true;
                }

                if($serverStats['lastPingResult'] && $serverStats['state'] === "starting"){
                    $rconrequired = false;
                    if(isset($serverPropertiesFile['enable-rcon'])){
                        if($serverPropertiesFile['enable-rcon'] == "true"){
                            $rconrequired = true;
                        }
                    }
                    if($rconrequired){
                        if($serverStats['lastRconPingResult']){
                            $serverStats['newoutput'] .= self::companion_echo("Info","Minecraft server online (Rcon)");
                            $serverStats['state'] = "online";
                        }
                    }
                    else{
                        $serverStats['newoutput'] .= self::companion_echo("Info","Minecraft server online");
                        $serverStats['state'] = "online";
                    }
                }

                //STOP MINECRAFT SERVER WHEN OFFLINE REQUESTED
                if($serverStats['requestedState'] === "stopped" && $serverStats['state'] === "online"){
                    $serverStats['requestedState'] = "";
                    if($processStats['running']){
                        $stopstring = "stop";
                        if(isset($serverInfo['stop_command'])){
                            $stopstring = $serverInfo['stop_command'];
                        }
                        $serverStats['newoutput'] .= self::companion_sendCommand($processPipes[0], $stopstring);
                        $serverStats['state'] = "stopping";
                    }
                }

                //CHECK IF COMMAND NEEDS TO BE SENT TO MINECRAFT SERVER
                if($serverStats['state'] === "online" && $serverStats['lastcommand'] !== ""){
                    $serverStats['newoutput'] .= self::companion_sendCommand($processPipes[0],$serverStats['lastcommand']);
                    $serverStats['lastcommand'] = "";
                }

                //CHECK IF PING INFO NEEDS REFRESHING
                $currentTime = time::stamp();
                if(($currentTime - $serverStats['lastPingCheck']) > 60 || $checkPing === true){
                    $checkPing = false;
                    $serverStats['lastPingCheck'] = $currentTime;

                    self::companion_echo("Info","Refreshing pings");

                    //Rcon ping
                    $serverStats['lastRconPingResult'] = false;
                    if(isset($serverPropertiesFile['enable-rcon'])){
                        if($serverPropertiesFile['enable-rcon'] == "true"){
                            if(isset($serverPropertiesFile['rcon.port'])){
                                $serverStats['lastRconPingResult'] = network::ping("localhost",$serverPropertiesFile['rcon.port'],0.1);
                            }
                        }
                    }
                    //MC ping
                    $serverStats['lastPingResult'] = false;
                    if(isset($serverPropertiesFile['server-port'])){
                        $serverStats['lastPingResult'] = network::ping("localhost",$serverPropertiesFile['server-port'],0.1);
                    }
                    else{
                        $port = "21" . $id;
                        if($id === "001"){
                            $port = "25565";
                        }
                        $serverStats['lastPingResult'] = network::ping("localhost",$port,0.1);
                    }
                }
            }
            //MAKE SURE EVERYTHING IS CLOSED AFTER BREAK
            fclose($socketServer);
            if(is_resource($process)){
                proc_close($process);
                fclose($processPipes[0]);
            }
            if(is_resource($backupProcess)){
                proc_close($backupProcess);
                fclose($backupProcessPipes[0]);
                fclose($backupProcessPipes[1]);
            }
        }
    }
    private static function companion_sendCommand($stdin,$data):string{
        $str = self::companion_echo("Info","Sending command: " . trim($data));
        if(!is_int(fwrite($stdin,$data . "\n"))){
            $str = self::companion_echo("Warning","Failed to send command");
        }
        return $str;
    }
    private static function companion_echo(string $type, string $text):string{
        $string = '[' . date("H:i:s") . '] [MCServer Monitor/' . $type . ']: ' . $text . "\n";
        echo $string;
        return $string;
    }
    //Server management
    public static function backupServer($id,$backupName = false,$askForOverwrite = false):bool{
        if(self::validateId($id,false)){
            if($backupName === false){
                $backupName = time::stamp();
            }
            $server = $id;
            $backupsPath = settings::read("backupsPath");
            $backupNameFile = $backupsPath . "\\" . $server . "\\" . $backupName . "_tmp";
            $finalBackupNameFile = substr($backupNameFile,0,-4);
            $fileTypes = array(".json"=>false,".rar"=>false);
            foreach($fileTypes as $fileType => $exists){
                if(is_file($finalBackupNameFile . $fileType)){
                    $fileTypes[$fileType] = true;
                }
            }
            $backupAllreadyExists = false;
            foreach($fileTypes as $exists){
                if($exists){
                    $backupAllreadyExists = true;
                }
            }
            $readyToBackup = true;
            if($backupAllreadyExists){
                if($askForOverwrite){
                    $readyToBackup = false;
                    echo "A mcservers backup with that name allready exists (" . $backupName . "), are you sure you want to overwrite it?";
                    if(user_input::yesNo()){
                        foreach($fileTypes as $fileType => $exists){
                            if($exists){
                                unlink($finalBackupNameFile . $fileType);
                            }
                        }
                        $readyToBackup = true;
                    }
                }
                else{
                    mklog('warning','Overwriting backup ' . $backupName . ' for server ' . $server,false);
                }
            }
            if($readyToBackup){
                $serverWasRunning = false;
                if(self::isOnline($server) || self::pingServer($server)){
                    $serverWasRunning = true;
                    self::stop($server);
                    sleep(settings::read("backupShutdownWaitTime"));
                }
                mklog('general','Backing up mcserver ' . $server . ' (' . $backupName . ')',false);
                json::writeFile($backupNameFile . ".json",array($server => settings::read("servers/" . $server)));
                e_winrar::dirToRar(settings::read('serversPath') . "\\" . $server,$backupNameFile . ".rar");
                foreach($fileTypes as $fileType => $exists){
                    rename($backupNameFile . $fileType,$finalBackupNameFile . $fileType);
                }
                $latestBackups = json::readFile($backupsPath . "\\latestBackups.json");
                $latestBackups[$server] = $backupName;
                json::writeFile($backupsPath . "\\latestBackups.json",$latestBackups,true);
                mklog('general','Finnished backup for server ' . $server . ' (' . $backupName . ')',false);
                if($serverWasRunning){
                    self::start($server);
                }
                return true;
            }
            else{
                mklog('warning','Skipping mcservers backup ' . $backupName . ' for server ' . $server,false);
            }
        }
        return false;
    }
    public static function backupServer2($id):bool{
        return self::sendCompanionData($id,"backup");
    }
    public static function start($id):bool{
        if(self::validateId($id,false)){
            return self::sendCompanionData($id,"start");
        }
        return false;
    }
    public static function stop($id):bool{
        return self::sendCompanionData($id,"stop");
    }
    public static function addMainServer($id):bool{
        if(self::validateId($id,false)){
            $servers = settings::read('mainServers');
            array_push($servers,$id);
            settings::set('mainServers',$servers,true);
            return true;
        }
        return false;
    }
    public static function removeMainServer($id):bool{
        if(self::validateId($id,false)){
            $servers = settings::read('mainServers');
            foreach($servers as $index => $server){
                if($server === $id){
                    unset($servers[$index]);
                }
            }
            settings::set('mainServers',$servers,true);
            return true;
        }
        return false;
    }
    public static function sendCommand($id,$command):bool{
        return self::sendCompanionData($id,"sendCommand",$command);
    }
    public static function deleteServer($id,$silent = false):bool{
        if(self::validateId($id,false)){
            $go = false;
            if($silent === true){
                $go = true;
            }
            else{
                echo "Are you sure you want to delete server " . $id . "?";
                if(user_input::yesNo()){
                    $go = true;
                }
            }
            if($go){
                $serversDir = settings::read("serversPath");
                files::ensureFolder($serversDir . "\\deleted");
                rename($serversDir . "\\" . $id,$serversDir . "\\deleted\\" . $id . "-" . time::stamp());
                settings::unset("servers/" . $id);
                return true;
            }
        }
        return false;
    }
    //All servers
    public static function allServers():array{
        $servers = [];
        foreach(settings::read('servers') as $serverId => $serverData){
            $servers[] = $serverId;
        }
        return $servers;
    }
    public static function serverDir():string{
        $path = settings::read('serversPath');
        if(substr($path,1,1) === ":"){
            return $path;
        }
        return getcwd() . '\\' . $path;
    }
    //Server process
    public static function getRootJavaProcess($pid){
        $childProcesses = system_api::getProcessChildProcesses($pid);
        if(count($childProcesses)>0){
            foreach($childProcesses as $name => $pid){
                if($name === "java.exe"){
                    $childs = system_api::getProcessChildProcesses($pid);
                    if(count($childs) === 0){
                        return $pid;
                    }
                    else{
                        return self::getRootJavaProcess($pid);
                    }
                }
            }
        }
    }
    //External
    public static function addSubdomainToServer($id):bool{
        if(self::validateId($id,false)){
            if(!settings::isset('servers/' . $id . '/cloudflareSRVid')){
                $port = "21" . $id;
                $name = "mc" . $id;
                if($id === "001"){
                    $port = "25565";
                    $name = "mc";
                }
                $srv = cloudflare_api::createSrvRecord($name,"_minecraft","_tcp",intval($port),"tomgriffiths.net");
                if(is_string($srv)){
                    settings::set('servers/' . $id . '/cloudflareSRVid',$srv);
                    return true;
                }
            }
        }
        return false;
    }
    public static function deleteSubdomainForServer($id):bool{
        if(self::validateId($id,false)){
            if(settings::isset('servers/' . $id . '/cloudflareSRVid')){
                $srv = cloudflare_api::deleteRecord(settings::read('servers/' . $id . '/cloudflareSRVid'));
                if($srv){
                    settings::unset('servers/' . $id . '/cloudflareSRVid');
                    return true;
                }
            }
        }
        return false;
    }
    //Content
    public static function addModrinthContentToServer($id, string $modId, string $modVersionId, string $type):string{
        $info = self::serverInfo($id);

        $types = array("mod","modpack","plugin","resourcepack","datapack");

        if(!in_array($type,$types)){
            return "Failed.\nIncorrect content type.";
        }
        
        $type1 = $type;
        if($type === "modpack"){
            $type1 = "mod";
        }
        if($info['capabilities']['allow' . ucfirst($type1) . 's'] !== true){
            return "Failed.\nServer does not allow " . $type1 . "s.";
        }

        $results = modrinth_api::downloadFile($modId, $modVersionId, $info['version']['version'], $info['version']['type']);

        if(is_array($results) && $results !== array()){
            //Pre go through and check if types are compatible
            foreach($results as $result){
                if($info['capabilities']['allow' . ucfirst($result['type']) . 's'] !== true){
                    return "Failed.\nDependency error.\n Server does not allow " . $result['type'] . "s.";
                }
            }

            $files = array();
            foreach($results as $result){
                $fileName = files::getFileName($result["file"]);
                $files[$fileName] = $result['type'];

                $destination = self::getTypeDestination($id,$result['type'],$result["file"]);

                if($destination === "server.properties"){
                    $newProperties['resource-pack'] = $result['url'];
                    $newProperties['resource-pack-sha1'] = sha1_file($result["file"]);
                    self::modifyServerPropertiesFile($id,$newProperties);
                    json::writeFile(self::serverDir() . "\\" . $id . "\\server.properties.resourcepack.json",array("custom"=>false,"projectId"=>$result["projectId"],"versionId"=>$result["versionId"]));
                }
                elseif(is_string($destination)){
                    if(files::copyFile($result["file"], $destination)){
                        json::writeFile($destination . ".json",array("custom"=>false,"projectId"=>$result["projectId"],"versionId"=>$result["versionId"]));
                    }
                    else{
                        return "Failed halfway.\nUnable to copy file:\n" . $fileName;
                    }
                }
                else{
                    return "Failed to get file destination.";
                }
            }
            $returnString = "Success!\nAll files successfully downloaded.\n";
            foreach($files as $file => $fileType){
                $returnString .= ucfirst($fileType) . ": " . $file . "\n";
            }
            return $returnString;
        }
        
        return "Failed.\nNo results returned or dependency incompatible with server.";
    }
    public static function listContents($id,$type):array|false{
        if(self::validateId($id,false)){
            $array = array();
            $destination = self::getTypeDestination($id, $type, "\\*");
            if(is_string($destination)){
                if($destination === "server.properties"){
                    $serverInfo = self::serverInfo($id);
                    if($serverInfo['capabilities']['hasPropertiesFile'] === true){
                        $serverProperties = self::parseServerPropertiesFile($id);
                        if(isset($serverProperties['resource-pack'])){
                            if($serverProperties['resource-pack'] !== ""){
                                $arrayFileNameKey = files::getFileName($serverProperties['resource-pack']);
                                $array[$arrayFileNameKey] = array("custom"=>true);
                            }
                        }
                    }
                    $resourcepackJson = self::serverDir() . "\\" . $id . "\\server.properties.resourcepack.json";
                    if(is_file($resourcepackJson)){
                        if(isset($arrayFileNameKey)){
                            $array[$arrayFileNameKey] = json::readFile($resourcepackJson,false,array());
                        }
                        else{
                            $array['server.properties.' . $type] = json::readFile($resourcepackJson,false,array());
                        }
                    }
                }
                else{
                    $contentFiles = glob($destination);
                    foreach($contentFiles as $contentFile){
                        $arrayFileNameKey = files::getFileName($contentFile);
                        $jsonFile = $contentFile . ".json";
                        if(is_file($jsonFile)){
                            $array[$arrayFileNameKey] = json::readFile($jsonFile,false,array());
                        }
                        else{
                            $array[$arrayFileNameKey] = array("custom"=>true);
                        }
                    }
                }
            }
            else{
                return false;
            }
            return $array;
        }
        return false;
    }
    public static function getTypeDestination($id, string $type, string $file):string|false{
        if(self::validateId($id,false)){
            $fileName = files::getFileName($file);
            $info = self::serverInfo($id);
            $serverDir = self::serverDir() . "\\" . $id;

            $types = array("mod","plugin","resourcepack","datapack");
            if(!in_array($type,$types)){
                return false;
            }

            if(!isset($info['capabilities'][$type . 'sFolder'])){
                return false;
            }
            $destination = $serverDir . '\\' . $info['capabilities'][$type . 'sFolder'] . '\\' . $fileName;

            if(strpos($destination,"%worldname%") !== false){
                $replacement = "world";
                if($info['capabilities']['hasPropertiesFile'] === true){
                    $replacement = self::parseServerPropertiesFile($id)['level-name'];
                }
                $destination = str_replace("%worldname%",$replacement,$destination);
            }

            if(strpos($destination,"{url}") !== false){
                $destination = false;
                if($type === "resourcepack"){
                    if($info['capabilities']['hasPropertiesFile'] === true){
                        return "server.properties";
                    }
                    else{
                        return false;
                    }
                }
                else{
                    return false;
                }
            }
            return $destination;
        }
        return false;
    }
}