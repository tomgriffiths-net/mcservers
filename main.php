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
            "hideServerStdx"         => true,
            "openServersInNewWindow" => false,
            "logServerMessages"      => true
        );
        foreach($defaultSettings as $settingName => $settingValue){
            settings::set($settingName, $settingValue, false);
        }
        
        mklog(1, 'Checking Java version');
        if(!self::whatJavaVersionIsInstalled()){
            echo 'Java JDK is not installed, would you like to install JDK 25 LTS?' . "\n";
            if(user_input::yesNo()){
                echo 'Downloading Java' . "\n";
                if(downloader::downloadFile('https://download.oracle.com/java/25/latest/jdk-25_windows-x64_bin.exe','temp\\latestJDK.exe')){
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
            if(!is_array($servers) || !is_string($serversDir)){
                mklog(2, 'Cannot upgrade server settings to server info file');
                return;
            }

            foreach($servers as $serverId => $serverInfo){
                if(!json::writeFile($serversDir . "\\" . $serverId . "\\mcserversInfo.json", $serverInfo, false)){
                    mklog(2, 'Failed to migrate server settings for server ' . $serverId);
                    continue;
                }

                mklog(1, 'Migrated settings for server ' . $serverId . ' to mcserversInfo.json inside its server folder');
                unset($servers[$serverId]);
            }

            if(empty($servers)){
                settings::unset('servers');
            }
            else{
                mklog(2, "Not all servers were converted to mcserversinfo file");
            }
        }

        //upgrade mcserversInfo.json to v2 for each server if not done allready
        if(!settings::isset("upgradedMcInfoToV2")){
            mklog(1, "Upgrading servers mcserverInfo to V2");

            $servers = self::allServers();
            if(!is_array($servers)){
                mklog(2, "Failed to get servers list");
                return;
            }

            $newServer = [];
            foreach($servers as $id){
                $server = self::serverInfo($id);
                if(!is_array($server)){
                    mklog(2, "Failed to read mcserversInfo.json for server " . $id);
                    continue;
                }

                if(!isset($server['version']['type']) || !isset($server['version']['version']) || !isset($server['version']['special_version'])){
                    mklog(2, "Insufficient version information to update mcserverInfo for server " . $id);
                    continue;
                }

                $newServer = self::serverTypeInfo($server['version']['type'], $server['version']['version'], $server['version']['special_version']);
                if(!is_array($newServer)){
                    mklog(2, "Failed to get updated spec for server " . $id . " with type " . $server['version']['type']);
                    continue;
                }

                $newServer['name'] = $server['name'];
                if($newServer['run']['jarFile'] === ""){
                    $newServer['run']['jarFile'] = basename(glob(self::serverDir($id) . "\\*.jar")[0]);
                }

                if(!self::setServerInfo($id, $newServer)){
                    mklog(2, "Failed to save new mcserversInfo.json for server " . $id);
                }
            }

            if(!settings::set("upgradedMcInfoToV2", true)){
                mklog(2, "Failed to save setting upgradedMcInfoToV2");
            }
        }
    }
    public static function command($line):void{
        $lines = str_getcsv($line, ' ', '"', "\\");
        if($lines[0] === "server"){
            if(isset($lines[1])){
                $servers = [];
                if($lines[1] === "all"){
                    $servers = self::allServers();
                    if(!is_array($servers)){
                        echo "Failed to get servers list\n";
                        return;
                    }
                }
                elseif($lines[1] === "main"){
                    $servers = settings::read('mainServers');
                    if(!is_array($servers)){
                        echo "Failed to get main servers list\n";
                        return;
                    }
                }
                elseif($lines[1] === "on" || $lines[1] === "off"){
                    $wantedState = $lines[1] === "on" ? "online" : "stopped";

                    $states = self::getManagerServerStates(false);
                    if(!is_array($states)){
                        echo "Failed to get server states\n";
                        return;
                    }

                    foreach($states as $server => $status){
                        if($status['state'] === $wantedState){
                            $servers[] = $server;
                        }
                    }
                    unset($server);
                }
                elseif(self::validateId($lines[1], false)){
                    $servers[] = $lines[1];
                }
                else{
                    echo "Unknown server group or id\n";
                    return;
                }
    
                if(empty($servers)){
                    echo "There are no servers that match the current criteria\n";
                    return;
                }
    
                if(isset($lines[2])){
                    if($lines[2] === "list"){
                        if(empty($servers)){
                            echo "There are no servers to list\n";
                            return;
                        }

                        $states = [];
                        $showRunning = false;
                        
                        if($lines[1] === "off" || $lines[1] === "on"){
                            foreach($servers as $server){
                                $states[$server] = ($lines[1] === "on" ? "online" : "stopped");
                            }
                            $showRunning = true;
                        }

                        if(!$showRunning && isset($lines[3]) && $lines[3] === "ping"){
                            echo "Getting server states...\n";
                            $states = self::getManagerServerStates(false);
                            if(!is_array($states)){
                                $states = [];
                            }
                            foreach($states as $server => $status){
                                $states[$server] = $status['state'];
                            }
                            $showRunning = true;
                        }

                        $columnNames = $showRunning ? ["Server ID"=>9, "Type"=>11, "Version"=>9, "State"=>7, "Name"=>26] : ["Server ID"=>9, "Type"=>11, "Version"=>10, "Name"=>30];
                        $rowsData = [];
                        foreach($servers as $server){
                            if(!self::validateId($server, true)){
                                continue;
                            }

                            $serverData = self::serverInfo($server);
                            if(!is_array($serverData)){
                                $serverData = [];
                            }

                            $lineItem = [];
                            $lineItem[] = $server;
                            $lineItem[] = isset($serverData['version']['type']) ? $serverData['version']['type'] : "Unknown";
                            $lineItem[] = isset($serverData['version']['version']) ? $serverData['version']['version'] : "Unknown";
                            if($showRunning){
                                $lineItem[] = isset($states[$server]) ? $states[$server] : "Unknown";
                            }
                            $lineItem[] = isset($serverData['name']) ? $serverData['name'] : "Unknown";

                            $rowsData[] = $lineItem;
                        }

                        echo commandline_list::table($columnNames, $rowsData);
                        return;
                    }
                }
    
                foreach($servers as $server){
                    if(isset($lines[2])){
                        if($lines[2] === "stop"){
                            if(!self::stop($server)){
                                echo "Failed to stop server\n";
                            }
                        }
                        elseif($lines[2] === "start"){
                            if(!self::start($server)){
                               echo "Failed to start server\n";
                            }
                        }
                        elseif($lines[2] === "backup"){
                            $backupName = time();
                            if(isset($lines[3])){
                                $lines[3] = strtolower($lines[3]);
                                if(preg_match("/^[a-z0-9]+$/", $lines[3]) === 1){
                                    $backupName = $lines[3];
                                }
                            }

                            $overwrite = (isset($lines[4]) && $lines[4] === "yes");
                            
                            if(!self::backupServer($server, $backupName, $overwrite)){
                                echo "Failed to backup server\n";
                            }
                        }
                        elseif($lines[2] === "sendcommand"){
                            if(isset($lines[3])){
                                array_shift($lines);
                                array_shift($lines);
                                $command = implode(" ",$lines);
                                if(!self::sendCommand($server,$command)){
                                    echo "Failed to send command to server\n";
                                }
                            }
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
                echo "Server or group not specified\n";
            }
        }
        elseif($lines[0] === "create"){
            array_shift($lines);

            $version = [];
            $serverData = [];

            foreach($lines as $line){
                $name = substr($line, 0, strpos($line, "="));
                $value = substr($line, strpos($line, "=") +1);

                if($name === "type"){
                    $version['type'] = $value;
                }
                elseif($name === "chan"){
                    $version['channel'] = $value;
                }
                elseif($name === "ver"){
                    $version['version'] = $value;
                }
                elseif($name === "sver"){
                    if(is_numeric($value) && strpos($value, ".") === false){
                        $value = intval($value);
                    }
                    $version['specialVersion'] = $value;
                }
                elseif($name === "name"){
                    $serverData['name'] = $value;
                }
                else{
                    echo "Unknown value name: " . $name . "\n";
                }
            }
            
            $server = self::createServer($version, $serverData);

            if(is_string($server)){
                echo "Created server " . $server . "\n";
            }
            else{
                echo "Failed to create server\n";
            }
        }
        else{
            echo "Command not found!\n";
        }
    }
    //Server information
    private static function readServerTypeInfo():array|false{
        $defaults = json::readFile('packages/mcservers/files/typeInfo.json');
        if(!is_array($defaults)){
            mklog(2, 'Failed to read server type information defaults');
            return false;
        }
        
        if(!isset($defaults['default']) || !is_array($defaults['default'])){
            mklog(2, 'Failed to read default server type information');
            return false;
        }
        if(!isset($defaults['getLatest']) || !is_array($defaults['getLatest'])){
            mklog(2, 'There is no type specific information on where to get the latest versions');
            return false;
        }

        if(!isset($defaults['types']) || !is_array($defaults['types'])){
            mklog(1, 'There is no type specific default server information, this is not normal');
            $defaults['types'] = [];
        }

        return $defaults;
    }
    public static function listKnownServerTypes():array|false{
        $defaults = self::readServerTypeInfo();
        if($defaults === false){return false;}
        $knownTypes = [];

        foreach($defaults['types'] as $type => $typeInfo){
            if(!is_string($type) || !is_array($typeInfo)){
                continue;
            }
            $typeNames = explode("+", $type);
            foreach($typeNames as $typeName){
                $typeArgs = explode(":", $typeName);
                if(!in_array($typeArgs[0], $knownTypes)){
                    $knownTypes[] = $typeArgs[0];
                }
            }
        }

        sort($knownTypes);

        return $knownTypes;
    }
    public static function serverTypeInfo(string $requestedType, string $requestedVersion, string|int $requestedSpecialVersion, $checkType=true):array|false{
        $defaults = self::readServerTypeInfo();
        if($defaults === false){return false;}

        $info = $defaults['default'];
        $validType = false;

        foreach($defaults['types'] as $type => $typeInfo){
            if(!is_string($type) || !is_array($typeInfo)){
                continue;
            }

            $typeNames = explode("+", $type);
            foreach($typeNames as $typeName){

                $typeArgs = explode(":", $typeName);

                if($typeArgs[0] !== $requestedType){
                    continue;
                }

                if(isset($typeArgs[1])){
                    if(self::compareMinecraftVersions($typeArgs[1], $requestedVersion) === 1){
                        continue;
                    }
                }

                if(isset($typeArgs[2])){
                    if(self::compareMinecraftVersions($typeArgs[2], $requestedSpecialVersion) === 1){
                        continue;
                    }
                }

                $info = self::arrayMergeRecursive($info, $typeInfo);
                $validType = true;
            }
        }

        if($checkType && !$validType){
            return false;
        }

        $info['version']['type'] = $requestedType;
        $info['version']['version'] = $requestedVersion;
        $info['version']['specialVersion'] = $requestedSpecialVersion;

        return $info;
    }
    public static function serverTypeGetLatestInfo(string $requestedType):array|false{
        $typeInfo = self::readServerTypeInfo();
        if(!is_array($typeInfo)){
            return false;
        }

        foreach($typeInfo['getLatest'] as $types => $functions){
            $types = explode("+", $types);

            if(in_array($requestedType, $types)){
                if(!is_array($functions) || !isset($functions['version']) || !is_string($functions['version'])){
                    return false;
                }

                return $functions;
            }
        }

        return false;
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
    public static function serverHasRcon(string $id):bool{
        $serverInfo = self::serverInfo($id);
        if(!is_array($serverInfo)){
            return false;
        }

        if($serverInfo['spec']['hasRcon']){
            if($serverInfo['spec']['hasPropertiesFile']){
                $properties = self::parseServerPropertiesFile($id);
                if(is_array($properties)){
                    return isset($properties['enable-rcon']) && ($properties['enable-rcon'] == "true");
                }
            }
            else{
                return true;
            }
        }

        return false;
    }
    public static function serverInfo(string $id):mixed{
        $serversPath = self::serverDir($id);
        if(is_string($serversPath)){
            return json::readFile($serversPath . "\\mcserversInfo.json", false);
        }

        return false;
    }
    private static function setServerInfo(string $id, array $info):bool{
        $serverDir = self::serverDir($id);
        if(!is_string($serverDir)){
            return false;
        }

        return json::writeFile($serverDir . "\\mcserversInfo.json", $info, true);
    }
    //Server properties file
    public static function serverPropertiesFileInfo():array|false{
        return json::readFile('packages/mcservers/files/serverPropertiesInfo.json');
    }
    public static function parseServerPropertiesFile(string $id):array|false{
        if(!self::validateId($id,false)){
            return false;
        }

        $info = self::serverInfo($id);
        if(!is_array($info)){
            return false;
        }

        if($info['spec']['hasPropertiesFile'] !== true){
            return false;
        }

        $serverDir = self::serverDir(); //Should be correct as serverinfo is an array

        return parse_ini_file($serverDir . "\\" . $id . "\\server.properties", true);
    }
    public static function writeServerPropertiesFile(string $id, array $data):bool{
        if(!self::validateId($id,false)){
            return false;
        }

        $info = self::serverInfo($id);
        if(!is_array($info)){
            return false;
        }

        if($info['spec']['hasPropertiesFile'] !== true){
            return false;
        }

        $serversDir = self::serverDir(); //Should be correct as serverinfo is an array

        return self::writeIniFile($serversDir . "\\" . $id . "\\server.properties", $data);
    }
    public static function editServerPropertiesFile(string $id, array $edits):bool{
        $existing = self::parseServerPropertiesFile($id);
        if(!is_array($existing)){
            return false;
        }

        $data = array_merge($existing, $edits);

        return self::writeServerPropertiesFile($id, $data);
    }
    //Server creation
    public static function createServer(array $version=[], array $serverData=[]):string|false{
        $version = self::makeVersionInfo($version);
        if(!is_array($version)){
            mklog(2, "Failed to populate version information");
            return false;
        }

        $customArgsWereSpecified = isset($serverData['run']['customArgs']) && is_string($serverData['run']['customArgs']);

        if(isset($serverData['configVersion'])){unset($serverData['configVersion']);}
        if(isset($serverData['version'])){unset($serverData['version']);}

        $defaultServerData = self::serverTypeInfo($version['type'], $version['version'], $version['specialVersion']);
        unset($version);
        $defaultServerData['name'] = ucfirst($defaultServerData['version']['type']) . " Server " . date("Y-m-d H:i:s");

        $serverData = self::appendData($defaultServerData, $serverData);
        unset($defaultServerData);
        
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
            mklog(2, 'It is suspected that the maximum number of servers has been reached (999)');
            return false;
        }

        mklog(1, 'Creating server with id ' . $server);

        $serverDir = $serversPath . "\\" . $server;
        
        if(!files::mkFolder($serverDir)){
            mklog(2, 'Failed to create server folder');
            return false;
        }

        if(is_string($serverData['setup']['installerFunction'])){
            if(!self::saferEval(self::tagServerInfo($serverData['setup']['installerFunction'], $serverData['version'], $serverDir))){
                mklog(2, "Failed to run installerFunction");
                return false;
            }
        }

        if(!self::runJarPathFunction($serverData, $serverDir)){
            return false;
        }

        $serverData = self::setMinJavaVersion($serverData);

        if(!$customArgsWereSpecified){
            $serverData['run']['customArgs'] .= self::defaultArgs($serverData['version']['type'], $serverData['version']['version'], $serverData['version']['specialVersion']);
        }

        if($serverData['spec']['hasEula']){
            if(!files::mkFile($serverDir . "\\eula.txt", "eula=true")){
                mklog(2, "Failed to write eula.txt");
                return false;
            }
        }

        if(!self::setServerInfo($server, $serverData)){
            mklog(2, 'Failed to save server information');
            return false;
        }

        if($serverData['setup']['startServerToMakeConfigFiles']){
            mklog(1, "Pre-running server " . $server . " to generate config files");

            if(!self::manage($server, "start", "prerun")){
                mklog(2, "Failed to pre-start server " . $server);
                return false;
            }

            foreach(["online", "stopped"] as $state){
                $tries = 0;
                while(self::serverStatus($server) !== $state){
                    sleep(1);
                    $tries ++;

                    if($tries > 60){
                        mklog(2, "Failed to pre-start server " . $server . " (waiting for " . $state . ")");
                        return false;
                    }
                }

                if($state === "online"){
                    if(!self::stop($server)){
                        mklog(2, "Failed to pre-start server " . $server . " (sending stop command)");
                        return false;
                    }
                }
            }

            $existingSettings = self::serverInfo($server);
            $existingSettings['setup']['startServerToMakeConfigFiles'] = false;
            self::setServerInfo($server, $existingSettings);
        }
        
        if($serverData['spec']['hasPropertiesFile']){
            if(!is_file($serverDir . "\\server.properties")){
                files::mkFile($serverDir . "\\server.properties", "");
            }

            $newProperties = [
                'server-port' => '21' . $server,
                'query.port' => '22' . $server,
                'rcon.port' => '23' . $server,
                'motd' => 'Server ' . $server,
                'difficulty' => 'normal',
                'view-distance' => '16',
                'enable-rcon' => 'true',
                'rcon.password' => self::randomStuff()
            ];

            if(!self::editServerPropertiesFile($server, $newProperties)){
                mklog(2, "Failed to edit server.properties");
                return false;
            }
        }

        foreach([
            "bind" => "0.0.0.0:21" . $server,
            "port" => (int) ("21" . $server),
            "host" => "0.0.0.0",
        ] as $setting => $value){
            if(isset($serverData['specialSettings'][$setting]) && is_array($serverData['specialSettings'][$setting])){
                if(!self::specialSetting($server, $setting, "write", $value, true)){
                    mklog(2, "Failed to edit custom " . $setting . " setting");
                    return false;
                }
            }
        }

        return $server;
    }
    public static function updateServer(string $id, array $version=[], bool $keepArgs=false):bool{
        $serverData = self::serverInfo($id);
        if(!is_array($serverData)){
            return false;
        }

        $version['type'] = $serverData['version']['type'];

        $serverData['version'] = self::makeVersionInfo($version);

        if($serverData['version']['type'] !== $version['type']){
            return false;
        }

        unset($version);

        $serverDir = self::serverDir($id);
        if(!is_string($serverDir)){
            return false;
        }

        if(!self::runJarPathFunction($serverData, $serverDir)){
            return false;
        }

        if(!$keepArgs){
            $serverData['run']['customArgs'] .= self::defaultArgs($serverData['version']['type'], $serverData['version']['version'], $serverData['version']['specialVersion']);
        }

        $serverData = self::setMinJavaVersion($serverData);

        return self::setServerInfo($id, $serverData);
    }
    private static function runJarPathFunction(array &$serverData, string $serverDir):bool{
        if(is_string($serverData['setup']['jarPathFunction'])){
            $jarPath = self::saferEval(self::tagServerInfo($serverData['setup']['jarPathFunction'], $serverData['version'], $serverDir));
            if(!is_string($jarPath)){
                mklog(2, "Failed to run jarPathFunction");
                return false;
            }

            $jarName = basename($jarPath);

            if(!files::copyFile($jarPath, $serverDir . "\\" . $jarName)){
                mklog(2, "Failed to copy jar file");
                return false;
            }

            if($serverData['run']['jarFile'] !== false){
                $serverData['run']['jarFile'] = $jarName;
            }
        }

        return true;
    }
    private static function setMinJavaVersion(array $serverData):array{
        $minJavaVersion = self::minJavaVersion($serverData['version']['type'], $serverData['version']['version'], $serverData['version']['specialVersion']);
        if(!is_int($minJavaVersion)){
            mklog(2, "Failed to read the required java version for server type " . $serverData['version']['type']);
            $serverData['run']['minJavaVersion'] = false;
        }
        else{
            $serverData['run']['minJavaVersion'] = $minJavaVersion;
        }
        if($minJavaVersion > 0){
            if(self::whatJavaVersionIsInstalled() < $minJavaVersion){
                mklog(2, "The installed java version does not meet the minimum requirements for " . $serverData['version']['type'] . " " . $serverData['version']['version'] . ", which requires Java " . $minJavaVersion);
            }
        }

        return $serverData;
    }
    public static function makeVersionInfo(array $version):?array{
        if(isset($version['type']) && is_string($version['type']) && isset($version['version']) && is_string($version['version']) && isset($version['specialVersion']) && in_array(gettype($version['specialVersion']), ["string", "integer"])){
            return $version;
        }
        
        if(!isset($version['type']) || !is_string($version['type'])){
            $version['type'] = "vanilla";
        }

        $getLatest = self::serverTypeGetLatestInfo($version['type']);
        if(!is_array($getLatest)){
            mklog(2, "Failed to get info on how to get the latest version of server type " . $version['type']);
            return null;
        }
        
        if(!isset($version['channel'])){
            $version['channel'] = null;
        }

        $version['channel'] = self::doChannelSelection($getLatest, $version['channel']);

        if(!isset($version['version']) || !is_string($version['version'])){
            if(!isset($getLatest['version']) || !is_string($getLatest['version'])){
                mklog(2, "There is no information on how to get the latest version for server type " . $version['type']);
                return null;
            }

            $result = self::saferEval(self::tagServerInfo($getLatest['version'], $version, ""));
            if(!is_string($result)){
                mklog(2, "Failed to run getLatest version function");
                return null;
            }
            $version['version'] = $result;
        }

        if(!isset($getLatest['specialVersionSuccessType']) || !is_string($getLatest['specialVersionSuccessType'])){
            $getLatest['specialVersionSuccessType'] = "string";
        }

        if(!isset($version['specialVersion']) || gettype($version['specialVersion']) !== $getLatest['specialVersionSuccessType']){
            if(isset($getLatest['specialVersion']) && is_string($getLatest['specialVersion'])){
                $result = self::saferEval(self::tagServerInfo($getLatest['specialVersion'], $version, ""));
                if(gettype($result) !== $getLatest['specialVersionSuccessType']){
                    mklog(2, "Failed to run getLatest specialVersion function");
                    return null;
                }
                $version['specialVersion'] = $result;
            }
        }

        if(!isset($version['specialVersion'])){
            if($getLatest['specialVersionSuccessType'] === "integer"){
                $version['specialVersion'] = 0;
            }
            else{
                $version['specialVersion'] = "";
            }
        }

        return $version;
    }
    public static function whatJavaVersionIsInstalled():int{
        $versionString = shell_exec("java --version");
        if(preg_match('/(?:java|openjdk)\s+(?:version\s+)?"?(\d+)/', $versionString, $matches)){
            return (int) $matches[1];
        }
        return 0;
    }
    private static function doChannelSelection(array $getLatest, ?string $wantedChannel=null):string{
        if(is_string($wantedChannel)){
            if(!isset($getLatest['channels']) || !is_array($getLatest['channels'])){
                mklog(2, "The current server type has no channels");
                return "";
            }
            if(in_array($wantedChannel, $getLatest['channels'])){
                return $wantedChannel;
            }
            else{
                mklog(2, "Invalid server type channel " . $wantedChannel . ", setting to default");
            }
        }
        
        if(isset($getLatest['channels']) && is_array($getLatest['channels']) && isset($getLatest['channels'][0]) && is_string($getLatest['channels'][0])){
            return $getLatest['channels'][0];
        }
        
        return "";
    }
    public static function listChannels(string $type):?array{
        $getLatest = self::serverTypeGetLatestInfo($type);
        if(!is_array($getLatest)){
            mklog(2, "Failed to get channels info for type " . $type);
            return null;
        }

        if(!isset($getLatest['channels']) || !is_array($getLatest['channels']) || !array_is_list($getLatest['channels'])){
            return [];
        }

        return $getLatest['channels'];
    }
    public static function listVersions(string $type, ?string $channel=null, ?string $after=null):?array{
        $getLatest = self::serverTypeGetLatestInfo($type);
        if(!is_array($getLatest)){
            mklog(2, "Failed to get info on how to get the latest version of server type " . $type);
            return null;
        }

        $channel = self::doChannelSelection($getLatest, $channel);

        if(!isset($getLatest['listVersions']) || !is_string($getLatest['listVersions'])){
            mklog(2, "The server type " . $type . " has no listVersions function");
            return null;
        }

        $versions = self::saferEval(self::tagServerInfo($getLatest['listVersions'], ['type'=>$type,'channel'=>$channel], ""));
        if(!is_array($versions)){
            mklog(2, "Failed to run listVersions function");
            return null;
        }

        if(is_string($after)){
            foreach($versions as $key => $version){
                $comparison = self::compareMinecraftVersions($version, $after);
                if($comparison === -2){
                    mklog(2, "Comparison failed");
                    return [];
                }
                if($comparison !== 1){
                    unset($versions[$key]);
                }
            }
        }

        return $versions;
    }
    public static function listSpecialVersions(string $type, string $version, ?string $channel=null):?array{
        $getLatest = self::serverTypeGetLatestInfo($type);
        if(!is_array($getLatest)){
            mklog(2, "Failed to get info on how to get the latest version of server type " . $type);
            return null;
        }

        $channel = self::doChannelSelection($getLatest, $channel);

        if(!isset($getLatest['listSpecialVersions']) || !is_string($getLatest['listSpecialVersions'])){
            mklog(2, "The server type " . $type . " has no listSpecialVersions function");
            return null;
        }

        $specialVersions = self::saferEval(self::tagServerInfo($getLatest['listSpecialVersions'], ['type'=>$type,'version'=>$version,'channel'=>$channel], ""));
        if(!is_array($specialVersions)){
            mklog(2, "Failed to run listSpecialVersions function");
            return null;
        }

        return $specialVersions;
    }
    public static function minJavaVersion(string $type, string $version, string|int $specialVersion):?int{
        $getLatest = self::serverTypeGetLatestInfo($type);
        if(!is_array($getLatest)){
            mklog(2, "Failed to get info for minimum java version to run server " . $type . "/" . $version);
            return null;
        }

        if(!isset($getLatest['minimumJavaVersion']) || !is_string($getLatest['minimumJavaVersion'])){
            mklog(1, "The selected type does not say what java version is required to run it");
            return 0;
        }

        $requiredVersion = self::saferEval(self::tagServerInfo($getLatest['minimumJavaVersion'], ['type'=>$type,'version'=>$version,'specialVersion'=>$specialVersion], ""));
        if(!is_numeric($requiredVersion)){
            mklog(2, "Failed to run minimumJavaVersion function for server type " . $type);
            return null;
        }

        return intval($requiredVersion);
    }
    public static function defaultArgs(string $type, string $version, string|int $specialVersion):string{
        $getLatest = self::serverTypeGetLatestInfo($type);
        if(!is_array($getLatest)){
            mklog(2, "Failed to get info for recommended arguments for server " . $type . "/" . $version);
            return "";
        }

        if(!isset($getLatest['customArgs']) || !is_string($getLatest['customArgs'])){
            return "";
        }

        $customArgs = self::saferEval(self::tagServerInfo($getLatest['customArgs'], ['type'=>$type,'version'=>$version,'specialVersion'=>$specialVersion], ""));
        if(!is_string($customArgs)){
            mklog(2, "Failed to run customArgs function for server type " . $type);
            return "";
        }

        return $customArgs;
    }
    //Data processing
    public static function tagServerInfo(string $string, array $version, string $serverdir):string{

        while(true){

            $innermost = self::tagServerInfo_innermost($string);
            if(!is_array($innermost)){
                break;
            }

            $start = $innermost['start'];
            $end = $innermost['end'];

            $tagParts = explode(":", substr($string, $start +1, $end - $start -1));

            //escapeing : with \
            $tag = [];
            $lastParts = "";
            foreach($tagParts as $part){
                if(substr($part, -1) === "\\"){
                    $lastParts .= $part . substr($part, 0, -1);
                }
                else{
                    $tag[] = $lastParts . $part;
                    $lastParts = "";
                }
            }
            unset($tagParts); unset($lastParts); unset($part);

            $replacement = false;

            if(in_array($tag[0], ["type", "version", "specialversion", "channel"])){
                if($tag[0] === "specialversion"){
                    $tag[0] = "specialVersion";
                }
                if(isset($version[$tag[0]])){
                    $replacement = $version[$tag[0]];
                }
            }
            elseif($tag[0] === "serverdir" || $tag[0] === "serverdiresc"){
                $replacement = $serverdir;
                if($tag[0] === "serverdiresc"){
                    $replacement = str_replace("\\","\\\\",$serverdir);
                }
            }
            elseif($tag[0] === "file"){
                if(isset($tag[1]) && is_file($serverdir . "\\" . $tag[1])){
                    $contents = file_get_contents($serverdir . "\\" . $tag[1]);
                    if(is_string($contents)){
                        $replacement = $contents;
                    }
                }
            }
            elseif($tag[0] === "yamlfile" || $tag[0] === "tomlfile"){
                if(isset($tag[1]) && is_file($serverdir . "\\" . $tag[1])){
                    if($tag[0] === "yamlfile"){
                        $contents = symfony_yaml_container::parseFile($serverdir . "\\" . $tag[1]);
                    }
                    else{
                        $contents = yosymfony_toml_container::parseFile($serverdir . "\\" . $tag[1]);
                    }

                    if(is_array($contents) && isset($tag[2])){

                        $value = settings::doAction($contents, $tag[2], "read");

                        if(is_bool($value)){
                            $value = $value ? "true" : "false";
                        }

                        if(in_array(gettype($value), ["string","integer","double"])){
                            $replacement = (string) $value;
                        }
                    }
                }
            }

            $replacement = str_replace("\n", " ", str_replace("\r", "", $replacement));

            $string = substr($string, 0, $start) . $replacement . substr($string, $end +1);
        }

        return $string;
    }
    private static function tagServerInfo_innermost(string $text):array|null{
        $len = strlen($text);
        
        for($i = 0; $i < $len; $i++){
            if($text[$i] === '<'){
                // Found an opening <, now look for the matching >
                // Make sure there's no nested < before the >
                
                for($j = $i + 1; $j < $len; $j++){
                    if($text[$j] === '<'){
                        // Found another < before >, so this isn't innermost
                        break;
                    }
                    if($text[$j] === '>'){
                        // Found > with no < in between - this is innermost
                        return [
                            'start' => $i,
                            'end' => $j,
                        ];
                    }
                }
            }
        }
        
        return null; // No <> found
    }
    public static function arrayMergeRecursive(array $a1, array $a2):array{
        foreach($a2 as $key => $value){
            if(is_array($value) && isset($a1[$key]) && is_array($a1[$key])){
                $a1[$key] = self::arrayMergeRecursive($a1[$key], $value);
            }
            else{
                $a1[$key] = $value;
            }
        }

        return $a1;
    }
    private static function appendData(array $cleanArray, array $newCrapArray):array{
        foreach($cleanArray as $cleanName => $cleanValue){
            if(isset($newCrapArray[$cleanName])){
                $cleanValueDatatype = gettype($cleanValue);
                $newCrapArrayItemDatatype = gettype($newCrapArray[$cleanName]);

                if($cleanValueDatatype === "array"){
                    if($cleanName === "specialSettings"){
                        $cleanArray[$cleanName] = self::arrayMergeRecursive($cleanValue, $newCrapArray[$cleanName]);
                    }
                    else{
                        $cleanArray[$cleanName] = self::appendData($cleanValue, $newCrapArray[$cleanName]);
                    }
                }
                else{
                    if($cleanValueDatatype === $newCrapArrayItemDatatype){
                        $cleanArray[$cleanName] = $newCrapArray[$cleanName];
                    }
                }
            }
        }

        return $cleanArray;
    }
    public static function randomStuff(int $length=16):string{
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        
        return $randomString;
    }
    public static function multiTypeRead(string $file, string $format):mixed{
        if(!is_file($file)){
            return null;
        }

        if($format === "json"){
            return json::readFile($file);
        }
        elseif($format === "text"){
            return file_get_contents($file);
        }
        elseif($format === "ini"){
            return parse_ini_file($file, true);
        }
        elseif($format === "yaml"){
            return symfony_yaml_container::parseFile($file);
        }
        elseif($format === "toml"){
            return yosymfony_toml_container::parseFile($file);
        }
        
        return null;
    }
    public static function multiTypeWrite(string $file, string $format, mixed $data, bool $overwrite=true):bool{
        if(is_file($file) && !$overwrite){
            return false;
        }

        if($format === "json"){
            return json::writeFile($file, $data);
        }
        elseif($format === "text"){
            if(is_bool($data)){
                $data = $data ? "true" : "false";
            }
            if(!is_string($data) && !is_int($data) && !is_float($data)){
                return false;
            }
            return files::mkFile($file, $data);
        }
        elseif($format === "ini"){
            if(!is_array($data)){
                return false;
            }
            return self::writeIniFile($file, $data);
        }
        elseif($format === "yaml"){
            if(!is_array($data)){
                return false;
            }
            $yaml = symfony_yaml_container::dump($data);
            if(!is_string($yaml)){
                return false;
            }
            return files::mkFile($file, $yaml);
        }
        elseif($format === "toml"){
            if(!is_array($data)){
                return false;
            }
            $toml = yosymfony_toml_container::arrayToToml($data);
            if(empty($toml)){
                return false;
            }
            return files::mkFile($file, $toml);
        }
        
        return false;
    }
    public static function writeIniFile(string $file, array $data):bool{
        $properties = "";
        foreach($data as $key => $value){
            if(!is_string($key)){
                continue;
            }

            $type = gettype($value);

            if($type === "boolean"){
                $value = ($value ? "true" : "false");
            }
            elseif(!in_array($type, ["string","integer","double","NULL"])){
                continue;
            }

            $properties .= "$key=$value\n";
        }

        return files::mkFile($file, $properties, "w", true);
    }
    public static function specialSetting(string $id, string $setting, string $action="read", mixed $value=null, bool $overwrite=false):mixed{
        $serverDir = self::serverDir($id);
        if(!is_string($serverDir)){
            return null;
        }

        $serverInfo = self::serverInfo($id);
        if(!is_array($serverInfo)){
            return null;
        }
        
        if(!isset($serverInfo['specialSettings'][$setting]) || !is_array($serverInfo['specialSettings'][$setting])){
            return null;
        }
        $settingInfo = $serverInfo['specialSettings'][$setting];

        if(!isset($settingInfo['file']) || !isset($settingInfo['format']) || !isset($settingInfo['setting'])){
            return null;
        }

        $settingFile = $serverDir . "\\" . $settingInfo['file'];

        if(!is_file($settingFile) && $action !== "write"){
            return null;
        }

        $existingData = [];

        if(is_file($settingFile)){
            $existingData = self::multiTypeRead($settingFile, $settingInfo['format']);
            if(!is_array($existingData)){
                mklog(2, "Failed to read existing config file " . $settingFile);
                return null;
            }
        }

        $result = settings::doAction($existingData, $settingInfo['setting'], $action, $value, $overwrite);

        if($action === "isset"){
            return $result;
        }

        if($action === "write" || $action === "unset"){
            if(!$result){
                mklog(2,"Failed to modify setting " . $settingInfo['setting'] . " in " . $settingFile);
                return null;
            }

            if(!self::multiTypeWrite($settingFile, $settingInfo['format'], $existingData, true)){
                mklog(2,"Failed to save " . $settingFile);
                return null;
            }

            return true;
        }

        return $result;
    }
    private static function saferEval(string $function):mixed{
        try{
            $result = eval('return ' . $function . ';');
        }
        catch(\Error){
            $result = null;
        }

        return $result;
    }
    /**
     * Compares two minecraft 1.x.x versions with support for -prex and -rcx.
     * 
     * @return int Returns 1 if v1 is larger, -1 if v2 is larger, 0 when equal, or -2 on error
     */
    public static function compareMinecraftVersions(string $v1, string $v2):int{
        // Check if both are simple integers
        if(ctype_digit($v1) && ctype_digit($v2)){
            return (int)$v1 <=> (int)$v2;
        }
        
        // Check if both are standard version numbers (dots and numbers only)
        $isStandardVersion1 = preg_match('/^[\d.]+$/', $v1);
        $isStandardVersion2 = preg_match('/^[\d.]+$/', $v2);
        
        if($isStandardVersion1 && $isStandardVersion2){
            return version_compare($v1, $v2);
        }
        
        // Anything else
        return minecraft_releases_api::compareVersionsUsingTimes($v1, $v2);
    }
    //Server status
    public static function getServerStats(string $id):array|false{
        if(self::validateId($id,false)){
            return self::sendCompanionData($id,"getStats");
        }
        return false;
    }
    public static function pingServer(string $id, float $timeout=0.2):bool{
        if(self::validateId($id,false)){
            $serverInfo = self::serverInfo($id);
            if($serverInfo['spec']['hasPropertiesFile']){
                $properties = self::parseServerPropertiesFile($id);
                if(is_array($properties) && isset($properties['server-port'])){
                    $port = $properties['server-port'];
                }
            }
            
            if(!isset($port)){
                $port = "21" . $id;
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
        return 'unknown';
    }
    public static function mirrorConsole(string $id, int $interval=1){
        while(true){
            echo self::sendCompanionData($id,"getStats")['newoutput'];
            sleep($interval);
        }
    }
    public static function sendCompanionData(string $id, string $action, string $payload=""):array|bool{
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

    //Manager
    //Run from client
    public static function manage(string $id, string $action, mixed $extra=null):array|false{
        if(!self::validateId($id, true)){
            return false;
        }

        return communicator_client::runfunction('mcservers::manager_run("' . $id . '", unserialize(base64_decode("' . base64_encode(serialize($action)) . '")), unserialize(base64_decode("' . base64_encode(serialize($extra)) . '")))');
    }

    //Run by communicator
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

    //Run by manage
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
                "prerun" => false
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

        $serverDir = self::serverDir($id);
        if(!is_string($serverDir)){
            return [
                'success' => false,
                'error' => "Unable to get server directory"
            ];
        }

        $serverInfo = self::serverInfo($id);
        if(!is_array($serverInfo)){
            return [
                'success' => false,
                'error' => "Unable to read server info"
            ];
        }

        if($action === "start" && $extra === "prerun"){
            if($serverInfo['setup']['startServerToMakeConfigFiles']){
                if(network::ping("localhost", $serverInfo['spec']['defaultPort'], 0.100)){
                    return [
                        'success' => false,
                        'error' => 'Port already in use'
                    ];
                }
                $localServerStats['prerun'] = $serverInfo['spec']['defaultPort'];
            }
            else{
                echo "Server " . $id . " cannot be run in prerun mode";
            }
        }

        if(isset($serverInfo['spec']['hasPropertiesFile'])){
            if($serverInfo['spec']['hasPropertiesFile']){
                if(time() - self::$serverPropertiesCache[$id]['time'] > 60){
                    self::$serverPropertiesCache[$id]['properties'] = self::parseServerPropertiesFile($id);
                    if(!is_array(self::$serverPropertiesCache[$id]['properties'])){
                        mklog(2, "Failed to read server properties file for server " . $id);
                        self::$serverPropertiesCache[$id]['properties'] = [];
                    }
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
                    $serverStats['newoutput'] .= self::manager_message(3, "Minecraft process unexpectedly stopped", $id);
                }
                foreach($localServerStats['pipes'] as &$pipe){
                    @fclose($pipe);
                    unset($pipe);
                }
                $exitCode = proc_close($localServerStats['proc']);
                $localServerStats['proc'] = null;

                $localServerStats['prerun'] = false;

                $serverStats['newoutput'] .= self::manager_message(2, "Minecraft process closed (" . $exitCode . ")", $id);

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
                $serverStats['newoutput'] .= self::manager_message(($backupExitCode ? 3 : 1), "Backup process closed (" . $backupExitCode . ")", $id);
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
            if(isset($serverPropertiesFile['enable-rcon']) && !is_int($localServerStats['prerun'])){
                if($serverPropertiesFile['enable-rcon']){
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
                $serverStats['newoutput'] .= self::manager_message(1, "Minecraft server online" . (is_int($localServerStats['prerun']) ? " (Prerun)" : ""), $id);
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

            if(isset($serverInfo['run']['minJavaVersion']) && is_int($serverInfo['run']['minJavaVersion'])){
                if($serverInfo['run']['minJavaVersion'] > 0){
                    $installedJavaVersion = self::whatJavaVersionIsInstalled();
                    if($installedJavaVersion < 1){
                        self::manager_message(2, "Failed to get the currently installed java version", $id);
                    }
                    elseif($serverInfo['run']['minJavaVersion'] > $installedJavaVersion){
                        return [
                            'success' => false,
                            'error' => "The server requires java version " . $serverInfo['run']['minJavaVersion'] . ", which is not installed"
                        ];
                    }
                }
            }
            else{
                self::manager_message(2, "It is unknown what java version this server requires", $id);
            }

            $descriptorspec[0] = ['pipe', 'r'];
            if(settings::read('hideServerStdx')){
                $descriptorspec[1] = ['file', 'NUL', 'w'];
                $descriptorspec[2] = ['file', 'NUL', 'w'];
            }

            $runString = self::manager_makeRunCommand($serverInfo, $serverDir);

            $localServerStats['proc'] = proc_open(
                $runString,
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
            if(isset($serverInfo['run']['stopCommand']) && is_string($serverInfo['run']['stopCommand']) && !empty($serverInfo['run']['stopCommand'])){
                $stopstring = $serverInfo['run']['stopCommand'];
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

            $backupName = (is_string($extra) && !empty($extra)) ? $extra : ((string) time());

            $backupsPath = settings::read("backupsPath");
            if(!is_string($backupsPath)){
                return [
                    'success' => false,
                    'error' => "Failed to read backupsPath setting"
                ];
            }

            $backupNameFile = $backupsPath . "\\" . $id . "\\" . $backupName;

            if(@unlink($backupNameFile . ".json") || @unlink($backupNameFile . ".rar")){
                mklog(1, 'Overwriting backup ' . $backupName . ' of server ' . $id);
            }
            else{
                mklog(1, 'Creating backup ' . $backupName . ' of server ' . $id);
            }

            if(!json::writeFile($backupNameFile . ".json", [$id => $serverInfo])){
                return [
                    'success' => false,
                    'error' => "Failed to save backup info file"
                ];
            }

            $latestBackups = json::readFile($backupsPath . "\\latestBackups.json", true, []);
            if(!is_array($latestBackups)){
                return [
                    'success' => false,
                    'error' => "Failed to read latest backups list"
                ];
            }

            $latestBackups[$id] = $backupName;

            if(!json::writeFile($backupsPath . "\\latestBackups.json", $latestBackups, true)){
                return [
                    'success' => false,
                    'error' => "Failed to write latest backups list"
                ];
            }

            $command = e_winrar::dirToRarCmd($serverDir, $backupNameFile . ".rar");
            if(!is_string($command)){
                return [
                    'success' => false,
                    'error' => "Failed to get rar command"
                ];
            }

            $localServerStats['backupProc'] = proc_open(
                $command,
                [0 => ['pipe', 'r'], 1 => ['file', 'NUL', 'w']],
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
    //Run by manager_run
    private static function manager_pings(string $id):void{
        self::$serverStats[$id]['lastPingCheck'] = time();

        self::manager_message(0, "Refreshing pings", $id);

        $serverPropertiesFile = self::$serverPropertiesCache[$id]['properties'];

        //Rcon ping
        self::$serverStats[$id]['lastRconPingResult'] = false;
        if(isset($serverPropertiesFile['enable-rcon']) && !(isset(self::$localServerStats[$id]['prerun']) && is_int(self::$localServerStats[$id]['prerun']))){
            if($serverPropertiesFile['enable-rcon']){
                if(isset($serverPropertiesFile['rcon.port'])){
                    self::$serverStats[$id]['lastRconPingResult'] = network::ping("localhost", $serverPropertiesFile['rcon.port'], 0.100);
                }
            }
        }

        //MC ping
        self::$serverStats[$id]['lastPingResult'] = false;

        if(isset(self::$localServerStats[$id]['prerun']) && is_int(self::$localServerStats[$id]['prerun'])){
            self::$serverStats[$id]['lastPingResult'] = network::ping("localhost", self::$localServerStats[$id]['prerun'], 0.100);
        }
        elseif(isset($serverPropertiesFile['server-port'])){
            self::$serverStats[$id]['lastPingResult'] = network::ping("localhost", $serverPropertiesFile['server-port'], 0.100);
        }
        else{
            $port = "21" . $id;

            $customBind = self::specialSetting($id, "bind", "read");
            if(is_string($customBind)){
                $colon = strpos($customBind, ":");
                if($colon){
                    $port = substr($customBind, $colon +1);
                }
            }

            self::$serverStats[$id]['lastPingResult'] = network::ping("localhost", $port, 0.100);
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
    private static function manager_makeRunCommand(array $serverInfo, string $serverDir):string{
        $run = &$serverInfo['run'];
        $versions = &$serverInfo['version'];

        if($run['customArgsOverwriteRun']){
            return self::tagServerInfo($run['customArgs'], $versions, $serverDir);
        }

        $command = "java ";

        if($run['maxMem'] > 0){
            $command .= "-Xmx" . $run['maxMem'] . "M ";
        }
        if($run['minMem'] > 0){
            $command .= "-Xms" . $run['minMem'] . "M ";
        }

        $command .= self::tagServerInfo($run['customArgs'], $versions, $serverDir) . " ";

        if(is_string($run['jarFile']) && !empty($run['jarFile'])){
            $command .= "-jar \"" . self::tagServerInfo($run['jarFile'], $versions, $serverDir) . "\" ";
        }

        if($run['hideGui']){
            $command .= "nogui ";
        }

        return substr($command, 0, -1);
    }

    //run by client through communicator
    public static function manager_getServerStates(bool $checkSettings=false):array|false{
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
                if(isset($info['run']['maxMem']) && is_int($info['run']['maxMem'])){
                    $states[$server]['memory'] = $info['run']['maxMem'];
                }
            }
        }

        return $states;
    }

    //Server management
    public static function backupServer(string $id, string $backupName="", bool $overwrite=false):bool{
        if(self::validateId($id,false)){
            mklog(2, "Failed to backup due to invalid server id");
            return false;
        }

        $backupsPath = settings::read("backupsPath");
        if(!is_string($backupsPath)){
            mklog(2, "Failed to read backupsPath setting, backup not created");
            return false;
        }

        $backupNameFile = $backupsPath . "\\" . $id . "\\" . $backupName;

        if((is_file($backupNameFile . ".json") || is_file($backupNameFile . ".rar")) && !$overwrite){
            mklog(2,'The backup ' . $backupName . ' for server ' . $id . ' already exists, backup not created');
            return false;
        }

        $originalState = self::serverStatus($id);
        if($originalState === "unknown"){
            mklog(2, 'Failed to get server ' . $id . ' state, backup not created');
            return false;
        }

        if($originalState === "backup"){
            mklog(2, 'The server ' . $id . ' is already in the backup state, backup not created');
            return false;
        }

        $serverWasRunning = false;
        if($originalState !== "stopped"){
            $serverWasRunning = true;
            mklog(1, "Stopping server " . $id . " for backup " . $backupName);

            if(!self::stop($id)){
                mklog(2, 'Failed to stop server ' . $id . ' for backup ' . $backupName . ', backup not created');
                return false;
            }

            $tries = 0;
            while(self::serverStatus($id) !== "stopped"){
                sleep(1);
                $tries ++;

                if($tries > 10){
                    mklog(2, 'Failed to wait for server ' . $id . ' to stop for backup ' . $backupName . ', backup not created');
                    return false;
                }
            }
        }

        if(!self::sendCompanionData($id, "backup", $backupName)){
            mklog(2, 'Failed to perform backup ' . $backupName . ' for server ' . $id);
            return false;
        }

        if($serverWasRunning){
            if(!self::start($id)){
                mklog(2, "Failed to resume server " . $id . " after backup");
            }
        }
        
        return true;
    }
    public static function start(string $id):bool{
        return self::sendCompanionData($id,"start");
    }
    public static function stop(string $id):bool{
        return self::sendCompanionData($id,"stop");
    }
    public static function addMainServer(string $id):bool{
        if(self::validateId($id,false)){
            return false;
        }

        $servers = settings::read('mainServers');
        if(!is_array($servers)){
            mklog(1, "Main servers list doesnt exist, creating it");
            $servers = [];
        }

        $servers[] = $id;

        return settings::set('mainServers', $servers, true);
    }
    public static function removeMainServer(string $id):bool{
        if(!self::validateId($id,false)){
            return false;
        }
        
        $servers = settings::read('mainServers');
        if(!is_array($servers)){
            mklog(1, "Main servers list doesnt exist");
            return false;
        }

        foreach($servers as $index => $server){
            if($server == $id){
                unset($servers[$index]);
            }
        }
        
        return settings::set('mainServers', $servers, true);
    }
    public static function sendCommand(string $id, string $command):bool{
        return self::sendCompanionData($id, "sendCommand", $command);
    }
    public static function deleteServer(string $id, bool $silent=false):bool{
        if(!self::validateId($id,false)){
            return false;
        }
        
        $go = $silent;
        if(!$go){
            echo "Are you sure you want to delete server " . $id . "?";
            $go = user_input::yesNo();
        }

        if(!$go){
            return false;
        }

        mklog(1, 'Deleting server ' . $id);

        $serversDir = self::serverDir();
        if(!is_string($serversDir)){
            mklog(2, 'Failed to read serversDir setting');
            return false;
        }

        if(!files::ensureFolder($serversDir . "\\deleted")){
            mklog(2, 'Failed to make deleted folder');
            return false;
        }

        if(!rename($serversDir . "\\" . $id,$serversDir . "\\deleted\\" . $id . "-" . time::stamp())){
            mklog(2, 'Failed to move server ' . $id . ' to deleted folder');
            return false;
        }

        return true;
    }
    //All servers
    public static function getManagerServerStates(bool $checkSettings=false):array|false{
        return communicator_client::runfunction('mcservers::manager_getServerStates(' . ($checkSettings ? 'true' : 'false') . ')');
    }
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
    public static function serverDir(string $id=""):string|false{
        $path = settings::read('serversPath');
        if(!is_string($path)){
            return false;
        }

        if(!files::ensureFolder($path)){
            return false;
        }

        $path = realpath($path);
        if($path === false){
            return false;
        }

        if(!empty($id)){
            if(!self::validateId($id, true)){
                return false;
            }
            $path .= "\\" . $id;
        }

        return $path;
    }
    //Server process
    public static function getRootJavaProcess(string|int $pid):string{
        $childProcesses = system_api::getProcessChildProcesses($pid);
        if(count($childProcesses)>0){
            foreach($childProcesses as $name => $pid){
                if($name === "java.exe"){
                    $childs = system_api::getProcessChildProcesses($pid);
                    if(count($childs) === 0){
                        return (string) $pid;
                    }
                    else{
                        return self::getRootJavaProcess($pid);
                    }
                }
            }
        }

        return "unknown";
    }
    public static function whatIsTheStartCommand(string $id):string|false{
        $serverDir = self::serverDir($id);
        if(!is_string($serverDir)){
            return false;
        }

        $serverInfo = self::serverInfo($id);
        if(!is_array($serverInfo)){
            return false;
        }

        return self::manager_makeRunCommand($serverInfo, $serverDir);
    }
    //Content
    public static function addModrinthContentToServer(string $id, string $projectId, string $versionId, string $type):string{
        $info = self::serverInfo($id);

        $types = ["mod","modpack","plugin","resourcepack","datapack"];

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

        $results = modrinth_api::downloadFile($projectId, $versionId, $info['version']['version'], $info['version']['type']);

        if(!is_array($results) || empty($results)){
            return "Failed.\nNo results returned or dependency incompatible with server.";
        }

        //Pre go through and check if types are compatible
        foreach($results as $result){
            if($info['abilities'][ucfirst($result['type']) . 's'] !== true){
                return "Failed.\nDependency error.\n Server does not allow " . $result['type'] . "s.";
            }
        }

        $files = [];
        foreach($results as $result){
            $fileName = files::getFileName($result["file"]);
            $files[$fileName] = $result['type'];

            $destination = self::getTypeDestination($id,$result['type'],$result["file"]);

            if($destination === "server.properties"){
                $serverProperties = self::parseServerPropertiesFile($id);
                if(!is_array($serverProperties)){
                    return "Failed.\nFailed to read existing server.properties";
                }

                $serverProperties['resource-pack'] = $result['url'];
                $serverProperties['resource-pack-sha1'] = sha1_file($result["file"]);
                if(!self::writeServerPropertiesFile($id, $serverProperties)){
                    return "Failed.\nFailed to save existing server.properties";
                }

                $serverDir = self::serverDir($id);
                if(!is_string($serverDir)){
                    return "Partially succeded.\nFailed to get server directory while saving resourcepack attributions";
                }

                if(!json::writeFile($serverDir . "\\server.properties.resourcepack.json", ["custom"=>false,"projectId"=>$result["projectId"],"versionId"=>$result["versionId"]])){
                    return "Partially succeded.\nFailed to save resourcepack attributions";
                }
            }
            elseif(is_string($destination)){
                if(files::copyFile($result["file"], $destination)){
                    if(!json::writeFile($destination . ".json", ["custom"=>false,"projectId"=>$result["projectId"],"versionId"=>$result["versionId"]])){
                        return "Failed halfway.\nUnable to save attributions for file:\n" . $fileName;
                    }
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
    public static function listContents(string $id, string $type):array|false{
        if(!self::validateId($id,false)){
            return false;
        }
        
        $array = [];
        $destination = self::getTypeDestination($id, $type, "\\*");
        if(!is_string($destination)){
            return false;
        }

        if($destination === "server.properties"){
            $serverProperties = self::parseServerPropertiesFile($id);
            if(is_array($serverProperties) && isset($serverProperties['resource-pack'])){
                if(!empty($serverProperties['resource-pack'])){
                    $arrayFileNameKey = basename($serverProperties['resource-pack']);
                    $array[$arrayFileNameKey] = ["custom"=>true];
                }
            }

            $serverDir = self::serverDir($id);
            if(is_string($serverDir)){
                $resourcepackJson = $serverDir . "\\server.properties.resourcepack.json";
                if(is_file($resourcepackJson)){
                    if(isset($arrayFileNameKey)){
                        $array[$arrayFileNameKey] = json::readFile($resourcepackJson, false, []);
                    }
                    else{
                        $array['server.properties.' . $type] = json::readFile($resourcepackJson, false, []);
                    }
                }
            }
        }
        else{
            $contentFiles = glob($destination);
            foreach($contentFiles as $contentFile){
                $arrayFileNameKey = basename($contentFile);
                $jsonFile = $contentFile . ".json";
                if(is_file($jsonFile)){
                    $array[$arrayFileNameKey] = json::readFile($jsonFile, false, []);
                }
                else{
                    $array[$arrayFileNameKey] = ["custom"=>true];
                }
            }
        }

        return $array;
    }
    public static function getTypeDestination(string $id, string $type, string $file):string|false{
        if(!self::validateId($id,false)){
            return false;
        }
        
        $fileName = basename($file);

        $info = self::serverInfo($id);
        if(!is_array($info)){
            return false;
        }

        $serverDir = self::serverDir($id);
        if(!is_string($serverDir)){
            return false;
        }

        $types = ["mod","plugin","resourcepack","datapack"];
        if(!in_array($type, $types)){
            return false;
        }

        if(!isset($info['abilities'][$type . 'sFolder']) || !is_string($info['abilities'][$type . 'sFolder'])){
            return false;
        }

        if(strtolower($info['abilities'][$type . 'sFolder']) === "url"){
            if($type !== "resourcepack"){
                return false;
            }
            if(!$info['spec']['hasPropertiesFile']){
                return false;
            }

            return "server.properties";
        }

        $destination = $serverDir . '\\' . $info['abilities'][$type . 'sFolder'] . '\\' . $fileName;

        if(strpos($destination, "%worldname%") !== false){
            $replacement = "world";
            if($info['spec']['hasPropertiesFile']){
                $serverProperties = self::parseServerPropertiesFile($id);
                if(is_array($serverProperties) && isset($serverProperties['level-name'])){
                    $replacement = $serverProperties['level-name'];
                }
            }
            $destination = str_replace("%worldname%", $replacement, $destination);
        }

        return $destination;
    }
}