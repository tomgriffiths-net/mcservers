<?php
class mcservers{
    private static $localServerStats = [];
    private static $serverStats = [];
    private static $serverPropertiesCache = [];
    private static $bypassCommunicatorRunRequrement = false;
    //CLI functions
    public static function init():void{
        $defaultSettings = array(
            "serversPath"            => "mcservers",
            "libraryPath"            => "mcservers\\library",
            "mainServers"            => [],
            "autostartMainServers"   => true,
            "backupsPath"            => "mcserverbackups",
            "backupShutdownWaitTime" => 30,
            "hideServerStdx"         => true,
            "openServersInNewWindow" => false,
            "logServerMessages"      => true
        );
        foreach($defaultSettings as $settingName => $settingValue){
            settings::set($settingName, $settingValue, false);
        }
        
        mklog(1, 'Checking Java version');
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

        //Move settings to mcserversInfo file if not done already.
        if(settings::isset('servers')){
            $servers = settings::read('servers');
            $serversDir = self::serverDir();
            if(is_array($servers) && is_string($serversDir)){
                foreach($servers as $serverId => $serverInfo){
                    if(json::writeFile($serversDir . "\\" . $serverId . "\\mcserversInfo.json", $serverInfo, false)){
                        mklog(1, 'Migrated settings for server ' . $serverId . ' to mcserversInfo.json inside its server folder');
                        unset($servers[$serverId]);
                    }
                    else{
                        mklog(2, 'Failed to migrate server settings for server ' . $serverId);
                    }
                }

                if(empty($servers)){
                    settings::unset('servers');
                }
            }
        }
    }
    public static function command($line):void{
        $lines = str_getcsv($line, ' ', '"', "\\");
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
        else{
            echo "Command not found!\n";
        }
    }
    public static function listServers(array $servers, bool $checkRunning=false, bool|int $expectedRunning=0):string{
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
                $serverData = self::serverInfo($server);
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
    //Server information
    public static function serverTypeInfo(string $type):array{
        $info = [
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
        ];

        if($type === "forge"){
            $info["allowMods"] = true;
            $info["usesInstaller"] = true;
        }
        elseif($type === "paper" || $type === "purpur"){
            $info["allowPlugins"] = true;
        }
        elseif($type === "velocity"){
            $info["allowPlugins"] = true;
            $info["allowDatapacks"] = false;
            $info["allowResourcepacks"] = false;
            $info["hasPropertiesFile"] = false;
            $info["hasEula"] = false;
        }

        return $info;
    }
    public static function validateId(string $id, bool $dontTestFile):bool{
        if(strlen($id) === 3 && preg_match("/^[0-9]+$/",$id)){
            if($dontTestFile){
                return true;
            }
            else{
                $serversPath = self::serverDir();
                if(is_string($serversPath)){
                    return is_file($serversPath . "\\" . $id . "\\mcserversInfo.json");
                }
            }
        }

        return false;
    }
    public static function parseServerData(array $serverData):array{
        $newServerData = array(
            'name'    => 'Server - ' . date("Y-m-d H:i:s"),

            'version' => array(
                'type'            => 'vanilla',
                'version'         => '1.21.11',
                'special_version' => ''
            ),

            'run'     => array(
                'max_ram_mb' => 2048,
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
    public static function serverHasRcon(string $id):bool{
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
    public static function serverInfo(string $id):mixed{
        if(self::validateId($id, false)){
            $serversPath = self::serverDir();
            if(is_string($serversPath)){
                return json::readFile($serversPath . "\\" . $id . "\\mcserversInfo.json", false);
            }
        }

        return false;
    }
    private static function setServerInfo(string $id, array $info):mixed{
        if(self::validateId($id, false)){
            $serversPath = self::serverDir();
            if(is_string($serversPath)){
                return json::writeFile($serversPath . "\\" . $id . "\\mcserversInfo.json", $info, true);
            }
        }

        return false;
    }
    //Server properties file
    public static function serverPropertiesFileInfo():array|false{
        return json::readFile('packages/mcservers/files/serverPropertiesInfo.json');
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
    public static function parseServerPropertiesFile(string $id):array{
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
    public static function modifyServerPropertiesFile(string $id,array $data):bool{
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
    public static function createServer(array $serverData=[]):bool|string{
        $serverData = self::parseServerData($serverData);
        
        $serversPath = self::serverDir();
        $server = 1;
        while($server < 999){
            $server = str_pad($server,3,"0",STR_PAD_LEFT);
            if(is_dir($serversPath . "\\" . $server) || is_file($serversPath . "\\" . $server)){
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

        mklog(1, 'Creating server with id ' . $server);

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
        elseif($serverData['version']['type'] === "waterfall"){
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
            json::writeFile($serverDir . "\\mcserversInfo.json", $serverData, true);
            txtrw::mktxt($serverDir . "\\eula.txt","eula=true",true);

            self::getServerStats($server);
            return $server;
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
    //Server status
    public static function getServerStats(string $id):array|false{
        if(self::validateId($id,false)){
            return self::sendCompanionData($id,"getStats");
        }
        return false;
    }
    public static function isOnline(string $id):bool{
        $result = self::getServerStats($id);
        if($result !== false){
            if($result['state'] === "online"){
                return true;
            }
        }
        return false;
    }
    public static function pingServer(string $id, float $timeout=0.2):bool{
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
    public static function serverStatus(string $id):string{
        $result = self::getServerStats($id);
        if($result !== false){
            return $result['state'];
        }
        return '';
    }
    public static function mirrorConsole(string $id, int $interval = 1){
        while(true){
            echo self::sendCompanionData($id,"getStats")['newoutput'];
            sleep($interval);
        }
    }
    public static function sendCompanionData(string $id, string $action, string $payload = ""):array|bool{
        if(!self::validateId($id, true)){
            return false;
        }

        $result = self::manage($id, $action, $payload);

        if(!is_array($result) || !isset($result['success']) || !$result['success']){
            return false;
        }

        if($action === "getStats"){
            return isset($result['stats']) ? $result['stats'] : false;
        }

        return true;
    }
    public static function manage(string $id, string $action, mixed $extra=null):array|false{
        if(!self::validateId($id, true)){
            return false;
        }

        return communicator_client::runfunction('mcservers::manager_run("' . $id . '", unserialize(base64_decode("' . base64_encode(serialize($action)) . '")), unserialize(base64_decode("' . base64_encode(serialize($extra)) . '")))');
    }

    public static function manager_start():void{
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        if(!isset($backtrace[2]['class']) || $backtrace[2]['class'] !== "communicator_server"){
            mklog(1, 'You cannot call manager_start outside of communicator_server');
            return;
        }

        if(settings::read("autostartMainServers")){
            $mainServers = settings::read("mainServers");
            if(is_array($mainServers)){
                self::$bypassCommunicatorRunRequrement = true;
                foreach($mainServers as $server){
                    self::manager_run($server, "start");
                }
                self::$bypassCommunicatorRunRequrement = false;
            }
        }
    }
    public static function manager_repeat():void{
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        if(!isset($backtrace[2]['class']) || $backtrace[2]['class'] !== "communicator_server"){
            mklog(1, 'You cannot call manager_repeat outside of communicator_server');
            return;
        }

        self::$bypassCommunicatorRunRequrement = true;
        foreach(self::$serverStats as $id => $serverStats){
            if((time() - $serverStats['lastPingCheck']) > 60){
                self::manager_pings($id);
            }

            self::manager_run($id, "thisIsNotAnAction"); //get server to update info
        }
        self::$bypassCommunicatorRunRequrement = false;
    }
    public static function manager_stop():void{
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        if(!isset($backtrace[2]['class']) || $backtrace[2]['class'] !== "communicator_server"){
            mklog(1, 'You cannot call manager_stop outside of communicator_server');
            return;
        }

        mklog(1, "Waiting for minecraft server processes to exit");


        foreach(self::$serverStats as $id => $stats){
            if($stats['state'] === "online"){
                self::$bypassCommunicatorRunRequrement = true;
                self::manager_run($id, "stop");
                self::$bypassCommunicatorRunRequrement = false;
            }
        }

        while(true){
            self::$bypassCommunicatorRunRequrement = true;
            foreach(self::$serverStats as $id => &$stats){
                self::manager_run($id, "thisIsNotAnAction"); //get server to update info

                if($stats['state'] === "stopped"){
                    unset(self::$serverStats[$id]);
                }
            }
            self::$bypassCommunicatorRunRequrement = false;

            if(empty(self::$serverStats)){
                break;
            }

            sleep(2);
        }
    }
    public static function communicatorServerThingsToDo():array{
        return [
            [
                "type" => "startup",
                "function" => 'mcservers::manager_start()'
            ],
            [
                "type" => "repeat",
                "interval" => 5,
                "function" => 'mcservers::manager_repeat()'
            ],
            [
                "type" => "shutdown",
                "function" => 'mcservers::manager_stop()'
            ],
        ];
    }
    public static function manager_run(string $id, string $action, mixed $extra=null):array{
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        if(!isset($backtrace[2]['class']) || $backtrace[2]['class'] !== "communicator_server"){
            if(!self::$bypassCommunicatorRunRequrement){
                mklog(1, 'You cannot call manager_repeat outside of communicator_server');
                return [];
            }
        }
        if(!self::validateId($id, false)){
            return [
                'success' => false,
                'error' => "Invalid server ID"
            ];
        }

        if(!isset(self::$localServerStats[$id])){
            self::$localServerStats[$id] = [
                "proc" => null,
                "pipes" => [],
                "backupProc" => null,
                "backupPipes" => [],
            ];
        }
        if(!isset(self::$serverStats[$id])){
            self::$serverStats[$id] = [
                "state" => "stopped",

                "pid" => "unknown",
                "cpu" => 0,
                "memory" => 0,

                "newoutput" => "",
                "lastcommand" => "",

                "lastStart" => 0,
                "lastStop" => 0,

                //Only done in repeat
                "lastPingResult" => false,
                "lastRconPingResult" => false,
                "lastPingCheck" => 0,
            ];
        }
        if(!isset(self::$serverPropertiesCache[$id])){
            self::$serverPropertiesCache[$id] = [
                'properties' => [],
                'time' => 0
            ];
        }

        $serverStats = &self::$serverStats[$id];
        $localServerStats = &self::$localServerStats[$id];
        $serverDir = self::serverDir() . "\\" . $id;

        $serverInfo = self::serverInfo($id);
        if(!is_array($serverInfo)){
            return [
                'success' => false,
                'error' => "Unable to read server info"
            ];
        }
        if(isset($serverInfo['capabilities']['hasPropertiesFile'])){
            if($serverInfo['capabilities']['hasPropertiesFile']){
                if(time() - self::$serverPropertiesCache[$id]['time'] > 60){
                    self::$serverPropertiesCache[$id]['properties'] = self::parseServerPropertiesFile($id);
                    self::$serverPropertiesCache[$id]['time'] = time();
                }
                $serverPropertiesFile = self::$serverPropertiesCache[$id]['properties'];
            }
        }

        //Update logs here so newoutput is in order
        if($action === "getStats"){
            //Read log file for console
            $logFile = $serverDir . "\\logs\\latest.log";
            if(is_file($logFile)){
                $newLines = implode("\n", self::manager_readNewLogLines($logFile));
                if(!empty($newLines)){
                    $serverStats['newoutput'] .= $newLines . "\n";
                }
            }
        }

        //Get process status
        $processStats = ['running'=>false, 'pid'=>"unknown"];
        if(is_resource($localServerStats['proc'])){
            $processStats = proc_get_status($localServerStats['proc']);
        }

        $backupProcessStats = ['running'=>false, 'pid'=>"unknown"];
        if(is_resource($localServerStats['backupProc'])){
            $backupProcessStats = proc_get_status($localServerStats['backupProc']);
        }

        //Check if process needs to be closed
        if(!$processStats['running']){
            if(is_resource($localServerStats['proc'])){
                if($serverStats['state'] !== "stopping"){
                    $serverStats['newoutput'] .= self::manager_message(2, "Minecraft process unexpectedly stopped", $id);
                }
                foreach($localServerStats['pipes'] as &$pipe){
                    @fclose($pipe);
                    unset($pipe);
                }
                $exitCode = proc_close($localServerStats['proc']);
                $localServerStats['proc'] = null;

                $serverStats['newoutput'] .= self::manager_message(1, "Minecraft process closed (" . $exitCode . ")", $id);

                $serverStats['lastStop'] = time::stamp();
                $serverStats['pid'] = "unknown";
                $serverStats['cpu'] = 0;
                $serverStats['memory'] = 0;
                $serverStats['state'] = "stopped";
            }
        }

        if(!$backupProcessStats['running']){
            if(is_resource($localServerStats['backupProc'])){
                foreach($localServerStats['backupPipes'] as &$pipe){
                    @fclose($pipe);
                    unset($pipe);
                }
                $backupExitCode = proc_close($localServerStats['backupProc']);
                $localServerStats['backupProc'] = null;
                $serverStats['newoutput'] .= self::manager_message(1, "Backup process closed (" . $backupExitCode . ")", $id);
                $serverStats['state'] = "stopped";
            }
        }

        //Update PID if needed
        if($serverStats['pid'] === "unknown" && $processStats['running'] && is_int($processStats['pid'])){
            $serverStats['pid'] = mcservers::getRootJavaProcess($processStats['pid']);
        }

        if($serverStats['state'] === "starting" || $serverStats['state'] === "stopping" || ($serverStats['state'] !== "online" && ($serverStats['lastPingResult'] || $serverStats['lastRconPingResult']))){
            self::manager_pings($id);
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
                    $serverStats['newoutput'] .= self::manager_message(1, "Minecraft server online (Rcon)", $id);
                    $serverStats['state'] = "online";
                }
            }
            else{
                $serverStats['newoutput'] .= self::manager_message(1, "Minecraft server online", $id);
                $serverStats['state'] = "online";
            }
        }

        //////////

        if($action === "getStats"){
            //Read log file for console
            $logFile = $serverDir . "\\logs\\latest.log";
            if(is_file($logFile)){
                $newLines = implode("\n", self::manager_readNewLogLines($logFile));
                if(!empty($newLines)){
                    $serverStats['newoutput'] .= $newLines . "\n";
                }
            }

            //Get process usages
            if($serverStats['pid'] !== "unknown"){
                if($processStats['running']){
                    $serverStats['memory'] = system_api::getProcessMemoryUsage($serverStats['pid']);
                    $serverStats['cpu'] = system_api::getProcessCpuUsage($serverStats['pid']);
                }
            }


            $newServerStats = self::$serverStats[$id];

            $serverStats['newoutput'] = "";

            return [
                'success' => true,
                'stats' => $newServerStats
            ];
        }
        elseif($action === "start"){
            if($serverStats['state'] !== "stopped" || $serverStats['lastPingResult'] || $serverStats['lastRconPingResult'] || is_resource($localServerStats['proc']) || is_resource($localServerStats['backupProc'])){
                return [
                    'success' => false,
                    'error' => "The server cannot be started"
                ];
            }

            if(!is_file($serverDir . "\\run.bat")){
                return [
                    'success' => false,
                    'error' => "The run.bat file was not found"
                ];
            }

            $descriptorspec[0] = ['pipe', 'r'];
            if(settings::read('hideServerStdx')){
                $descriptorspec[1] = ['file', 'NUL', 'w'];
                $descriptorspec[2] = ['file', 'NUL', 'w'];
            }

            $localServerStats['proc'] = proc_open(
                file_get_contents($serverDir . "\\run.bat"),
                $descriptorspec,
                $localServerStats['pipes'],
                $serverDir,
                null,
                (settings::read("openServersInNewWindow") ? ["create_new_console"=>true] : [])
            );

            if(!is_resource($localServerStats['proc'])){
                $serverStats['newoutput'] .= self::manager_message(3, "Failed to start Minecraft server process", $id);
                return [
                    'success' => false,
                    'error' => "Failed to open the server process"
                ];
            }

            $serverStats['newoutput'] .= self::manager_message(1, "Started Minecraft server process", $id);
            $serverStats['state'] = "starting";
            $serverStats['lastStart'] = time();
            return [
                'success' => true
            ];
        }
        elseif($action === "stop"){
            if($serverStats['state'] !== "online" || !$processStats['running']){
                return [
                    'success' => false,
                    'error' => "The server is not running"
                ];
            }

            $stopstring = "stop";
            if(isset($serverInfo['stop_command']) && is_string($serverInfo['stop_command']) && !empty($serverInfo['stop_command'])){
                $stopstring = $serverInfo['stop_command'];
            }

            if(!self::manager_sendCommandToServer($id, $stopstring)){
                $serverStats['newoutput'] .= self::manager_message(2, "Failed to send stop command to server", $id);
                return [
                    'success' => false,
                    'error' => "Failed to send stop command"
                ];
            }

            $serverStats['newoutput'] .= self::manager_message(1, "Sent stop command to server", $id);
            $serverStats['state'] = "stopping";
            return [
                'success' => true,
            ];
        }
        elseif($action === "sendCommand"){
            if(!is_string($extra) || empty($extra)){
                return [
                    'success' => false,
                    'error' => "Empty command"
                ];
            }

            if(!self::manager_sendCommandToServer($id, $extra)){
                $serverStats['newoutput'] .= self::manager_message(2, "Failed to send command", $id);
                return [
                    'success' => false,
                    'error' => "Failed to send command"
                ];
            }

            $serverStats['newoutput'] .= self::manager_message(1, "Sent command to server", $id);
            return [
                'success' => true,
            ];
        }
        elseif($action === "backup"){
            if($serverStats['state'] !== "stopped" || is_resource($localServerStats['backupProc']) || is_resource($localServerStats['proc'])){
                return [
                    'success' => false,
                    'error' => "The server is not in the stopped state"
                ];
            }

            $localServerStats['backupProc'] = proc_open(
                'php\\php.exe cli.php command "mcservers server ' . $id . ' backup" no-loop true',
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w']],
                $backupProcessPipes
            );

            if(!is_resource($localServerStats['backupProc'])){
                $serverStats['newoutput'] .= self::manager_message(3, "Failed to start Backup process", $id);
                return [
                    'success' => false,
                    'error' => "Failed to start backup process"
                ];
            }

            $serverStats['newoutput'] .= self::manager_message(1, "Backup process started", $id);
            $serverStats['state'] = "backup";
            return [
                'success' => true,
            ];
        }
        elseif($action === "kill"){
            if(is_resource($localServerStats['proc'])){
                if(proc_terminate($localServerStats['proc'])){
                    return [
                        'success' => true
                    ];
                }
                else{
                    return [
                        'success' => false,
                        'error' => "Failed to send terminate command to minecraft server"
                    ];
                }
            }
            if(is_resource($localServerStats['backupProc'])){
                if(proc_terminate($localServerStats['backupProc'])){
                    return [
                        'success' => true
                    ];
                }
                else{
                    return [
                        'success' => false,
                        'error' => "Failed to send terminate command to backup process"
                    ];
                }
            }
            return [
                'success' => false,
                'error' => "Nothing is running right now"
            ];
        }
        
        return [
            'success' => false,
            'error' => "Unknown action"
        ];
    }
    private static function manager_pings(string $id):void{
        self::$serverStats[$id]['lastPingCheck'] = time();

        self::manager_message(0, "Refreshing pings", $id);

        $serverPropertiesFile = self::$serverPropertiesCache[$id]['properties'];

        //Rcon ping
        self::$serverStats[$id]['lastRconPingResult'] = false;
        if(isset($serverPropertiesFile['enable-rcon'])){
            if($serverPropertiesFile['enable-rcon'] == "true"){
                if(isset($serverPropertiesFile['rcon.port'])){
                    self::$serverStats[$id]['lastRconPingResult'] = network::ping("localhost", $serverPropertiesFile['rcon.port'], 0.1);
                }
            }
        }
        //MC ping
        self::$serverStats[$id]['lastPingResult'] = false;
        if(isset($serverPropertiesFile['server-port'])){
            self::$serverStats[$id]['lastPingResult'] = network::ping("localhost", $serverPropertiesFile['server-port'], 0.1);
        }
        else{
            $port = "21" . $id;
            if($id === "001"){
                $port = "25565";
            }
            self::$serverStats[$id]['lastPingResult'] = network::ping("localhost", $port, 0.1);
        }
    }
    private static function manager_sendCommandToServer(string $id, string $command):bool{
        if(empty($command) || !self::validateId($id, true) || !isset(self::$localServerStats[$id])){
            return false;
        }

        if(!isset(self::$localServerStats[$id]['pipes'][0]) || !is_resource(self::$localServerStats[$id]['pipes'][0])){
            return false;
        }

        return (bool) fwrite(self::$localServerStats[$id]['pipes'][0], $command . "\n");
    }
    private static function manager_message(int $type, string $text, string $id):string{
        $type = min(max($type, 0), 3);
        $string = '[' . date("H:i:s") . '] [MCSM ' . $id . '/' . ["Verbose","Info","Warning","Error"][$type] . ']: ' . $text . "\n";
        if($type){
            echo cli_formatter::formatLine($string, ["green","yellow","red"][$type-1], false, false);
        }
        elseif(verboseLogging()){
            echo $string;
        }

        if(settings::read("logServerMessages")){
            ob_start();
            mklog(min($type, 2), "ManagerMessage: " . $text);
            ob_end_clean();
        }

        return $string;
    }
    private static function manager_readNewLogLines(string $file):array{
        // Use a static variable to persist position between calls
        static $handles = [];

        clearstatcache(false, $file);

        // Initialize tracking for this file if not already
        if(!isset($handles[$file])){
            if(!file_exists($file)){
                return ["[Log file not found: $file]"];
            }
            $fp = fopen($file, "r");
            if(!$fp){
                return ["[Unable to open $file]"];
            }
            fseek($fp, 0, SEEK_END); // start at end
            $handles[$file] = [
                'fp' => $fp,
                'pos' => ftell($fp),
                'size' => filesize($file),
            ];
            return []; // nothing to return on first call
        }

        // Pull existing info
        $fp = $handles[$file]['fp'];
        $lastPos = $handles[$file]['pos'];
        $lastSize = $handles[$file]['size'];

        $currentSize = filesize($file);

        // Detect file reset or rotation
        if($currentSize < $lastSize){
            fclose($fp);
            $fp = fopen($file, "r");
            $lastPos = 0;
        }

        fseek($fp, $lastPos);

        $newLines = [];
        while(($line = fgets($fp)) !== false){
            $newLines[] = rtrim($line, "\r\n");
        }

        $handles[$file] = [
            'fp' => $fp,
            'pos' => ftell($fp),
            'size' => $currentSize,
        ];

        return $newLines;
    }

    //Server management
    public static function backupServer(string $id, string|false $backupName=false, bool $askForOverwrite=false):bool{
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
                json::writeFile($backupNameFile . ".json",array($server => self::serverInfo($server)));
                e_winrar::dirToRar(self::serverDir() . "\\" . $server,$backupNameFile . ".rar");
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
    public static function backupServer2(string $id):bool{
        return self::sendCompanionData($id,"backup");
    }
    public static function start(string $id):bool{
        if(self::validateId($id,false)){
            return self::sendCompanionData($id,"start");
        }
        return false;
    }
    public static function stop(string $id):bool{
        return self::sendCompanionData($id,"stop");
    }
    public static function addMainServer(string $id):bool{
        if(self::validateId($id,false)){
            $servers = settings::read('mainServers');
            array_push($servers,$id);
            settings::set('mainServers',$servers,true);
            return true;
        }
        return false;
    }
    public static function removeMainServer(string $id):bool{
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
    public static function sendCommand(string $id, string $command):bool{
        return self::sendCompanionData($id,"sendCommand",$command);
    }
    public static function deleteServer(string $id, bool $silent=false):bool{
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
                $serversDir = self::serverDir();
                files::ensureFolder($serversDir . "\\deleted");
                rename($serversDir . "\\" . $id,$serversDir . "\\deleted\\" . $id . "-" . time::stamp());
                return true;
            }
        }
        return false;
    }
    //All servers
    public static function allServers():array|false{
        $servers = [];
        $serversDir = self::serverDir();
        if(!is_string($serversDir)){
            return false;
        }

        $files = glob($serversDir . '/[0-9][0-9][0-9]/mcserversInfo.json');
        if(!is_array($files)){
            return false;
        }

        foreach($files as $path){
            $maybeId = basename(dirname($path));
            if(self::validateId($maybeId, true)){
                $servers[] = $maybeId;
            }
        }

        return $servers;
    }
    public static function manager_getStates(bool $checkSettings=false):array|false{
        $allServers = self::allServers();
        if(!is_array($allServers)){
            return false;
        }

        $states = [];
        foreach($allServers as $server){
            $states[$server]['state'] = (isset(self::$serverStats[$server]) ? self::$serverStats[$server]['state'] : "stopped");

            if($checkSettings){
                $info = self::serverInfo($server);
                if(!is_array($info)){
                    continue;
                }

                if(isset($info['name']) && is_string($info['name'])){
                    $states[$server]['name'] = $info['name'];
                }
                if(isset($info['version']['type']) && is_string($info['version']['type'])){
                    $states[$server]['type'] = $info['version']['type'];
                }
                if(isset($info['version']['version']) && is_string($info['version']['version'])){
                    $states[$server]['version'] = $info['version']['version'];
                }
                if(isset($info['run']['max_ram_mb']) && is_int($info['run']['max_ram_mb'])){
                    $states[$server]['memory'] = $info['run']['max_ram_mb'];
                }
            }
        }

        return $states;
    }
    public static function serverDir():string|false{
        $path = settings::read('serversPath');
        if(!is_string($path)){
            return false;
        }

        files::ensureFolder($path);

        return realpath($path);
    }
    //Server process
    public static function getRootJavaProcess(string|int $pid){
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

        return "unknown";
    }
    //External
    public static function addSubdomainToServer(string $id):bool{
        if(self::validateId($id,false)){
            $serverInfo = self::serverInfo($id);
            if(!isset($serverInfo['cloudflareSRVid'])){
                $port = "21" . $id;
                $name = "mc" . $id;
                if($id === "001"){
                    $port = "25565";
                    $name = "mc";
                }
                $srv = cloudflare_api::createSrvRecord($name,"minecraft","tcp",intval($port),"tomgriffiths.net");
                if(is_string($srv)){
                    $serverInfo['cloudflareSRVid'] = $srv;
                    self::setServerInfo($id, $serverInfo);
                    return true;
                }
            }
        }
        return false;
    }
    public static function deleteSubdomainForServer(string $id):bool{
        if(self::validateId($id,false)){
            $serverInfo = self::serverInfo($id);
            if(isset($serverInfo['cloudflareSRVid'])){
                if(cloudflare_api::deleteRecord($serverInfo['cloudflareSRVid'])){
                    unset($serverInfo['cloudflareSRVid']);
                    self::setServerInfo($id, $serverInfo);
                    return true;
                }
            }
        }
        return false;
    }
    //Content
    public static function addModrinthContentToServer(string $id, string $modId, string $modVersionId, string $type):string{
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
    public static function listContents(string $id, string $type):array|false{
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
    public static function getTypeDestination(string $id, string $type, string $file):string|false{
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