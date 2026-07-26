# mcservers
This is a package for PHP-CLI that is able to manage local minecraft servers.
<br>

For function documentation see https://www.tomgriffiths.net/php-cli/docs/packages/mcservers.html.

---
<br>

# Commands
All commands start with "mcservers", some functionality is only available through function calls or the website package.

## Selectors
- **all**: Selects all servers.
- **main**: Selects the servers in the mainServers setting.
- **on**: Selects servers that are currently on.
- **off**: Selects servers that are currently off.
- **[server id]**: Selects a specific server id.

## Command info
- **server [selector] list (ping)**: This prints a table of server information to the console, the ping option can be specified to also include on/off information.
- **server [selector] start/stop**: Starts or stops the selected servers.
- **server [selector] backup ([backup name]) (yes)**: Creates a RAR backup of the selected servers, by default the backup name is the Unix time stamp, otherwise the name can be specified with [backup name] but is optional, the "yes" option can also be specified to force overwrite a named backup.
- **server [selector] sendcommand [command]**: Sends a specified command to the selected servers, the command does not need to be in quotes as it is anything following sendcommand.

- **create (options)**: Creates a server with the specified options, options are: chan= (update channel), type= (server type), ver= (minecraft version), sver= (special version e.g. a build number).

## Full command examples
- **mcservers server all start**: Starts all the minecraft servers.
- **mcservers server on stop**: Stops all running minecraft servers.
- **mcservers server 001 backup weekly yes**: Creates a backup for server 001 called "weekly" and overwrites the old "weekly" backup.
- **mcservers server main sendcommand say hi**: Tells all the main servers to run "/say hi" in their consoles.
- **mcservers create**: Creates a default server.
- **mcservers create chan=snapshot**: Creates a server with the latest vanilla snapshot.
- **mcservers create type=paper chan=alpha**: Creates a paper server with the latest version, allowing for alpha builds
- **mcservers create type=paper ver=1.21.11 sver=110**: Creates a paper server with the minecraft version 1.21.11 using build 110.

---
<br>

# String replacements
Some string values can have replacements in them, kind of like code in the string but not code. These are in the format &lt;thing&gt; such as &lt;version&gt; which will be replaced as the servers version,
this can be usefull for things like referencing a jar path with the servers version as the name. Some replacements can have arguments, like &lt;file:path/to/file&gt; where it will be replaced with the contents of the file.
The name of the replacement and its arguments are seperated with a colon (:), if there are many arguments there will also be a colon between them. The colon can be escaped with \: to write a colon as part of the value of an argument rather than seperating the arguments.
If a replacement doesnt exist or the number of expected arguments is wrong, it will be ignored and left as is.

| Name           | What is it?                                                                                                                                       | Arguments                            | Example usage                                                        | Example replacement                                  |
|----------------|---------------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------|----------------------------------------------------------------------|------------------------------------------------------|
| type           | The type of the minecraft server.                                                                                                                 | none                                 | &lt;type&gt;                                                         | paper                                                |
| version        | The version of the minecraft server.                                                                                                              | none                                 | &lt;version&gt;                                                      | 1.21.11                                              |
| specialversion | The special version of the minecraft server.                                                                                                      | none                                 | &lt;specialversion&gt;                                               | 110                                                  |
| channel        | The channel that is currently being used to get server update information.                                                                        | none                                 | &lt;channel&gt;                                                      | STABLE                                               |
| serverdir      | The directory of the minecraft server.                                                                                                            | none                                 | &lt;serverdir&gt;                                                    | C:\\MyServers\\001                                   |
| serverdiresc   | The directory of the minecraft server but backslashes are escaped for use within code.                                                            | none                                 | &lt;serverdiresc&gt;                                                 | C:\\\\MyServers\\\\001                               |
| file           | Reads a file relative to the servers directory, requires that the file exists to work.                                                            | file path                            | &lt;file:logs\latest.log&gt;                                         | [13:58:25] [ServerMain/INFO]: Loaded 1470 recipes... |
| yamlfile       | Reads a value from inside a yaml file relative to the servers directory, the value has to be of type bool, string, int or float (double) to work. | file path, settings style value name | &lt;yamlfile:config\paper-global.yml:chunk-system/worker-threads&gt; | -1                                                   |
| tomlfile       | Reads a value from inside a toml file relative to the servers directory, the value has to be of type bool, string, int or float (double) to work. | file path, settings style value name | &lt;tomlfile:velocity.toml:bind&gt;                                  | 0.0.0.0:25565                                        |

Note: Settings style value name is the name of the value where array keys are seperated by forward slashes, the same format that the settings package uses for setting names.
<br>

These can be used in most places that take a string.
- Server Data
    - setup/installerFunction
    - setup/jarPathFunction
    - run/customArgs
    - run/jarFile
- Getlatest Data
    - version
    - specialVersion
    - listVersions
    - listSpecialVersions
    - minimumJavaVersion
    - customArgs

---
<br>

# Server Data
Server data is the information stored in the mcserversInfo.json file that is in the root of a specific servers folder, this information defines how the server behaves and what it can do.

- **configVersion**: An integer specifying the version of the server data format, do not change this.
- **name**: A string containing the servers name.
- **version**: An array containing the version information for the server, do not change this.
    - **type**: A string specifying the type of minecraft server.
    - **version**: A string specifying the minecraft version of the server.
    - **specialVersion**: A string or integer specifying the sub version, this is type specific and can be used for things like build number within the same minecraft version.
- **run**: An array containing information on how to run the server.
    - **maxMem**: An integer specifying the maximum memory in megabytes the server can use.
    - **minMem**: An integer specifying the minimum memory in megabytes the server has to use.
    - **hideGui**: A boolean specifying weather to pass the nogui option to the minecraft server.
    - **jarFile**: A string specifying the jar file to run to start the server, only change this if you know what you are doing.
    - **customArgsOverwriteRun**: A boolean specifying weather the customArgs overwrite the regular run command, if true the customArgs is run as a command rather than passed as jvm arguments.
    - **customArgs**: A string specifying custom jvm arguments or a custom command, see customArgsOverwriteRun.
    - **minJavaVersion**: An integer specifying the minimum major java version a server requires to run, do not change this unless you have good reason to and know what you are doing.
    - **stopCommand**: A string specifying the command sent to the minecraft server to get it to stop.
- **setup**: An array containing information on how to set up and update the server.
    - **jarPathFunction**: A string/boolean containing some code that returns where mcservers can find the correct jar file that should be put into the servers folder or false if there is no code to run.
    - **installerFunction**: A string/boolean containing some code that is run to install any required server software or false if there is no code to run, the code must return a truthy value on success.
    - **startServerToMakeConfigFiles**: A boolean specifying weather the server should be started during initial setup to generate config files, this does not stop config file modification during setup.
- **abilities**: An array containing what the server can do, things like mods and plugins.
    - **mods**: A boolean specifying weather the server supports mods.
    - **modsLimit**: An integer specifying the limit of mods the server can have.
    - **modsFolder**: A string specifying where to store the mods.
    - **plugins**: A boolean specifying weather the server supports plugins.
    - **pluginsLimit**: An integer specifying the limit of plugins the server can have.
    - **pluginsFolder**: A string specifying where to store the plugins.
    - **datapacks**: A boolean specifying weather the server supports datapacks.
    - **datapacksLimit**: An integer specifying the limit of datapacks the server can have.
    - **datapacksFolder**: A string specifying where to store the datapacks.
    - **resourcepacks**: A boolean specifying weather the server supports resourcepacks.
    - **resourcepacksLimit**: An integer specifying the limit of resourcepacks the server can have.
    - **resourcepacksFolder**: A string specifying where to store the resourcepacks.
- **spec**: An array containing information about the current server type.
    - **hasPropertiesFile**: A boolean specifying weather the server has a standard server.properties file.
    - **hasEula**: A boolean specifying weather the server has a standard eula.txt file.
    - **hasRcon**: A boolean specifying weather the server has Rcon.
    - **defaultPort**: A integer specifying the default port the server has before it is customised.
- **specialSettings**: An array containing information on special settings the server has.

See the serverData example at the bottom of this readme.
<br>

Note: The modsFolder, pluginsFolder, datapacksFolder and resourcepacksFolder can have %worldname% in the string and it will be replaced with the world name the server has.
resourcepacksFolder can also be set to "url" for setting the resourcepack url in the server.properties.

## Special Settings
Special settings can be set in the specialSettings part of serverData, special settings are settings this server has that most dont.
Some special settings are used internally if set such as bind, host and port.
<br>

Each setting needs to have file and format specified, the file is a path relative to the server directory, the format can be json, text, ini, yaml or toml.
The formats that are not text also need a setting path set with a settings style value name, see string replacements note.
A default can also be set that will override the default set in the specialSettingsInfo.json file, this is used for setting custom server port defaults.
<br>

Here is an example of the bind special setting for a waterfall server:

```json
"specialSettings": {
    "bind": {
        "file": "config.yml",
        "format": "yaml",
        "setting": "listeners/0/host"
    }
}
```

---
<br>

# specialSettingsInfo.json
specialSettingsInfo.json is located in the files folder in the mcservers package folder. It contains information on some special settings,
a special setting does not need an entry in this file but it can be useful. Each element in the array is keyed so its name is the special setting
it represents, and its values describe the setting. Each value inside the array is optional. These are not enforced when using specialSetting(),
they are more recommendations or guidelines.

- **desc**: A string containing the decription of the setting.
- **type**: A string containing the data type of the setting, compared against gettype().
- **default**: A default value for the setting, this can be ovewritten with the default inside the specialSetting in the server data.

Type specific options:
<br>

String:

- **pattern**: A string containing a regex to compare the value string to.

Integer or double:

- **min**: An integer/double specifying the minimum value (inclusive) the setting can have.
- **max**: An integer/double specifying the maximum value (inclusive) the setting can have.

---
<br>

# typeInfo.json
typeInfo.json is located in the files folder in the mcservers package folder. It contains all the information on how mcservers should treat specific types of minecraft servers.

## default
default is an array inside typeInfo.json that contains the default serverData applied to all servers on creation.

## types
types is an array inside typeInfo.json where each of its elements are string keyed arrays where the values are overrides for the default serverData.
An elements key is the type of server with optional version and special version seperated with colon where only servers that match or exceed the version will have the override applied,
and multiple types can be put together with a plus. The colons and pluses are optional and you could just specify "forge" or "paper" on its own as the key.
<br>

Here is an example demonstrating all combination things, this override will only apply to forge servers and paper servers that are above 1.21.1.
This translates to all forge servers of any version and paper servers at or above version 1.21.2 have 4096MB of memory rather than the default 2048MB.

```json
"types": {
    "forge+paper:1.21.2": {
        "run": {
            "maxMem": 4096
        }
    }
}
```

Note: The plus seperates the types and the versions attached to those types, so "forge+paper:1.21.2" is seperated to (forge) and (paper 1.21.2 and above), not forge and paper where both are 1.21.2 or above.
If it were "forge:1.21.5+paper:1.21.2" it would apply the overrides for (forge 1.21.5 and above) and (paper 1.21.2 and above).

## getLatest
getlatest is an array inside typeInfo.json and holds the Getlatest Data, this is where mcserver goes to fetch update and version information about server types.
It is similar to the types array where the keys are server types, but only pluses are supported here, the colon version selection is not.
<br>

Data things (their absence means there is no code that does that):
- **version**: A string containing some code that gets the latest minecraft version the server type has and returns it as a string.
- **listVersions**: A string containing some code that returns an array list of the available minecraft versions the server type has.
- **specialVersion**: A string containing some code that gets the latest special version the server type has.
- **specialVersionSuccessType**: A string containing the gettype() of the return success type for specialVersion, the default is string but this can be set to any type, e.g. "integer" for paper build numbers.
- **listSpecialVersions**: A string containing some code that returns an array list of the available special versions the server type has for a specific minecraft version.
- **channels**: An array that contains a list of channels the server type can get updates from, the channels are of type string, the first channel is the default and the order should be most stable to least stable, as the items above the selected channel are included, see the example for papermc below, if beta is selected, that will include beta and any channels above it, which will be beta and stable, so selecting beta simply enables beta releases being available as well as stable.
- **customArgs**: A string containing some code that gets the recommended custom jvm arguments for the current server type and returns them as a string.
- **minimumJavaVersion**: A string containing some code that gets the minimum required major java version that is required to run the server, it should return a integer or numeric string or float, version numbers with 2 dots do not work here.

Example for servers from papermc:

```json
"getLatest": {
    "paper+velocity+waterfall": {
        "version": "papermc_downloads_api_v3::listVersions('<type>')[0]",
        "listVersions": "papermc_downloads_api_v3::listVersions('<type>')",
        "specialVersion": "max(papermc_downloads_api_v3::listBuilds('<type>','<version>','<channel>'))",
        "specialVersionSuccessType": "integer",
        "listSpecialVersions": "papermc_downloads_api_v3::listBuilds('<type>','<version>','<channel>')",
        "channels": [
            "stable",
            "beta",
            "alpha"
        ],
        "customArgs": "implode(' ', papermc_downloads_api_v3::customJavaInfo('<type>','<version>')['flags']['recommended'])",
        "minimumJavaVersion": "papermc_downloads_api_v3::customJavaInfo('<type>','<version>')['version']['minimum']"
    }
}
```

---
<br>

# Server manager
The server manager is the process that runs inside communicator_server to run and keep track of every aspect of every minecraft server.
The commands are sent via manage() function or using communicator_client customAction with package mcservers, action manage, and the server id, manage command, and extra set as args 0, 1 and 2.
The managers response will be an array with a success set, which is a boolean indicating success in running the command, there is sometimes an error string set which can contain a reason for a false success.

## Commands
- **getStats**: Gets the stats of the specified server, see Server stats below, the stats are sent in a "stats" array sent with the response.
- **start**: Starts a server.
- **stop**: Stops a server.
- **sendCommand**: Sends a minecraft server console command to a server, this requires the extra data in the command to be a string of the command that will be sent to the server.
- **backup**: Makes a backup of the server, the extra data can optionally be a string specifying a specific backup name, otherwise the backup name will be a unix time stamp of the time it was made. The backup name will be put into the latestBackups.json file in the backups folder.
- **kill**: Kills the minecraft server process or the backup process, whichever is running, if both are somehow running, it will kill the minecraft server first and a second go will be needed to get the backup process.

## Server stats
Server stats is the data sent back from the server manager in the "stats" array in the response.
- **state**: A string containing the servers state, it can be stopped, starting, online, stopping or backup.
- **pid**: An integer specifying the pid of the minecraft server, or a string "unknown" if the minecraft server does not have a pid.
- **cpu**: An integer specifying the cpu percentage the minecraft server is currently using.
- **memory**: An integer specifying how much memory in MB the minecraft server is currently using.
- **newoutput**: A string containing the new messages since the last time getStats was run.
- **lastStart**: An integer specifying the last time the server was started in unix time stamp.
- **lastStop**: An integer specifying the last time the server was stopped in unix time stamp.
- **lastPingResult**: A boolean containing the result of the last ping to the minecraft servers port.
- **lastRconPingResult**: A boolean containing the result of the last Rcon ping to the minecraft server.
- **lastPingCheck**: An integer specifying the last time pings were sent out in unix time stamp.

---
<br>

# Server Data example
Here is an example of the mcserversInfo.json file that holds the server data for a paper 1.21.11 server:

```json
{
    "configVersion": 2,
    "name": "Paper Server 2026-02-23 13:53:53",
    "version": {
        "type": "paper",
        "version": "1.21.11",
        "specialVersion": 117
    },
    "run": {
        "maxMem": 2048,
        "minMem": 0,
        "hideGui": true,
        "jarFile": "paper-1.21.11-117.jar",
        "customArgsOverwriteRun": false,
        "customArgs": "-XX:+AlwaysPreTouch -XX:+DisableExplicitGC -XX:+ParallelRefProcEnabled -XX:+PerfDisableSharedMem -XX:+UnlockExperimentalVMOptions -XX:+UseG1GC -XX:G1HeapRegionSize=8M -XX:G1HeapWastePercent=5 -XX:G1MaxNewSizePercent=40 -XX:G1MixedGCCountTarget=4 -XX:G1MixedGCLiveThresholdPercent=90 -XX:G1NewSizePercent=30 -XX:G1RSetUpdatingPauseTimePercent=5 -XX:G1ReservePercent=20 -XX:InitiatingHeapOccupancyPercent=15 -XX:MaxGCPauseMillis=200 -XX:MaxTenuringThreshold=1 -XX:SurvivorRatio=32",
        "minJavaVersion": 21,
        "stopCommand": "stop"
    },
    "setup": {
        "jarPathFunction": "papermc_downloads_api_v3::jarPath('<type>', '<version>', <specialversion>);",
        "installerFunction": false,
        "startServerToMakeConfigFiles": false
    },
    "abilities": {
        "mods": false,
        "modsLimit": 0,
        "modsFolder": "mods",
        "plugins": true,
        "pluginsLimit": 0,
        "pluginsFolder": "plugins",
        "datapacks": true,
        "datapacksLimit": 0,
        "datapacksFolder": "%worldname%\/datapacks",
        "resourcepacks": true,
        "resourcepacksLimit": 1,
        "resourcepacksFolder": "url"
    },
    "spec": {
        "hasPropertiesFile": true,
        "hasEula": true,
        "hasRcon": true,
        "defaultPort": 25565
    },
    "specialSettings": []
}
```
