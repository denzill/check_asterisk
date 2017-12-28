# check_asterisk

[Icinga2](https://www.icinga.com/) or [Nagios](https://www.nagios.org/) plugin for checking asterisk status, detect long calls, disconnected SIP peers and get some stats.

Tested with [PHP >= 5.4.16](http://php.net/), [Asterisk 13](https://www.asterisk.org/) and [Icinga2](https://www.icinga.com/)

## Installation

- Copy [check_asterisk.php](check_asterisk.php) to [Icinga2](https://www.icinga.com/) or [Nagios](https://www.nagios.org/) `PluginDir`
- Copy [asterisk-commnand.conf](icinga/asterisk-command.conf), [asterisk-group.conf](icinga/asterisk-group.conf) and [asterisk-service.conf](icinga/asterisk-service.conf) to `Icinga2 config dir/conf.d`, usually `/etc/icinga2/conf.d`
- Add new user in manager.conf from [manager.conf](asterisk/manager.conf)
  - for more accurate detection of disconnected SIP peers add `qualify=yes` to peer definition

## How to use
```
$ check_asterisk.php -H <hostname> -P <port> -u <user> -p <password> -t <seconds> [-w unconnected WARNING] [-c unconnected CRITICAL] [-m] [-i] [-W long call WARNING] [-C long call CRITICAL] [-I] [-v] [-l logfile]
```

### Options summary
- `-H <hostname>` - Asterisk [AMI](https://wiki.asterisk.org/wiki/pages/viewpage.action?pageId=4817239) hostname
- `-P <port>` - Asterisk [AMI](https://wiki.asterisk.org/wiki/pages/viewpage.action?pageId=4817239) port
- `-u <username>` - Username
- `-p <password>` - Password
- `-t <read timeout>` - Timeout read from [AMI](https://wiki.asterisk.org/wiki/pages/viewpage.action?pageId=4817239)
- `[-v]` - Verbose output (to stdout and <logfile>)
- `[-w #]` - Unconnected peers WARNING threshold (in seconds)
- `[-c #]` - Unconnected peers CRITICAL threshold (in seconds)
- `[-m]` - Check only *monitored* peers for connected/disconnected state
- `[-i]` - Do not set CRITICAL/WARNING state for unconnected peers
- `[-W #]` - Long call WARNING threshold (in seconds)
- `[-C #]` - Long call CRITICAL threshold (in seconds)
- `[-I]` - Do not set CRITICAL/WARNING state for long calls
- `[-l logfile]` - log output to file (relative to /var/log/)

`<option>` - required option, `[option]` - optional option

## TODO
- [x] Disconnected peers detect
- [x] Logging to file
- [x] Describe options on [asterisk-commnand.conf](icinga/asterisk-commnand.conf)
- [x] Long call detection
