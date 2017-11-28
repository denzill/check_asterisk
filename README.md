# check_asterisk

Icinga/Nagios plugin for checking asterisk status, detect long calls, disconnected SIP peers and get some stats.

Tested with **PHP 5.6** and **Asterisk 13.X**

## Installation

- Copy [check_asterisk.php](check_asterisk.php) to Icinga/Nagios `PluginDir`
- Copy [asterisk-commnand.conf](icinga/asterisk-command.conf) and [asterisk-service.conf](icinga/asterisk-service.conf) to `Icinga config dir/conf.d`, usually `/etc/icinga2/conf.d`
- Add new user in manager.conf from [manager.conf](asterisk/manager.conf)

## How to use
```
$ check_asterisk.php -H <hostname> -P <port> -u <user> -p <password> -t <seconds> [-w unconnected WARNING] [-c unconnected CRITICAL] [-W long call WARNING] [-C long call CRITICAL] [-v] [-l logfile]
```

### Options summary
- `-H <hostname>` - Asterisk [AMI](https://wiki.asterisk.org/wiki/pages/viewpage.action?pageId=4817239) hostname
- `-P <port>` - Asterisk [AMI](https://wiki.asterisk.org/wiki/pages/viewpage.action?pageId=4817239) port
- `-u <username>` - Username
- `-p <password>` - Password
- `-t <read timeout>` - Timeout read from [AMI](https://wiki.asterisk.org/wiki/pages/viewpage.action?pageId=4817239)
- `[-v]` - Verbose output (to stdout)
- `[-w #]` - Unconnected peers WARNING threshold
- `[-c #]` - Unconnected peers CRITICAL threshold
- `[-W #]` - Long call WARNING threshold (in seconds) (<span style="color:red">**NOT IMPLEMENTED!**</span>)
- `[-C #]` - Long call CRITICAL threshold (in seconds) (<span style="color:red">**NOT IMPLEMENTED!**</span>)
- `[-l logfile]` - log output to file (relative to /var/log/)

**\<option\>** - required option, **[option]** - optional option

## TODO
- [x] Disconnected peers detect
- [x] Logging to file
- [ ] Long call detection
- [ ] Describe options on [asterisk-commnand.conf](icinga/asterisk-commnand.conf)
