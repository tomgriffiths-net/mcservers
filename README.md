# mcservers
This is a package for PHP-CLI that is able to manage local minecraft servers.

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
- **mcservers server 001 backup weekly shutup**: Creates a backup for server 001 called "weekly" and overwrites the old "weekly" backup.
- **mcservers server main sendcommand say hi**: Tells all the main servers to run "/say hi" in their consoles.
- **mcservers create**: Creates a default server.
- **mcservers create chan=snapshot**: Creates a server with the latest vanilla snapshot.
- **mcservers create type=paper chan=alpha**: Creates a paper server with the latest version, allowing for alpha builds
- **mcservers create type=paper ver=1.21.11 sver=110**: Creates a paper server with the minecraft version 1.21.11 using build 110.
