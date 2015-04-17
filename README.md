Shift8 provides a simple mechanism over the AJAM asterisk interface. Apart from the obvious reason of providing an easy way to talk with a remote asterisk server, the main benefit of having an intermediate library between a software and an Asterisk server, is mostly that you can isolate the remote Asterisk, protecting the credentials and the entire PBX

> Features

* Shift8 supports pretty much all asterisk manager commands
* Provides a Event listener mechanism, so that when an event occurs to notify a part of a system. The Event listener can also contain a filter to notify the listener for specific events only
* Provides a Debug listener to assist in the debugging of the application your are creating. (Currently containing a Dummy (stdout) and a syslog debug listener)
* Provides a Queue manager interface. Using Shift8 one could create a two part application that instead of executing a command directly to the remote asterisk, would queue the command via a Queue Processor, and another application would run, execute the commands in Queue for a specific interval, and store the results into the same Queue for the 'frontend' application to retrieve
* Supports a common (as much as possible) infrastructure for all results coming from the remote asterisk via Shift8_Event
* Shift8 library is properly documented and all the return values from the internal functions are created in a way to make the library SOAP discoverable.

> Prerequisites

* Asterisk (with AJAM interface) Shift8 has been developed under Asterisk 1.6 but I don't think there will be any problems with version 1.4
* PHP5
* PHP5 Curl extension (apt-get install php5-curl under debian/ubuntu)
* Optional: PHP5 Mysql extension (used by the Shift8 Queue Mysql Processor)

> Installation

You must initially enable the manager interface in the asterisk by editing asterisk/manager.conf. You need to set:
```
enabled = yes
webenabled = yes
```

You also need to create a manager for Shift8 to connect with:
```
[manager]
secret=secret
deny=0.0.0.0/0.0.0.0
permit=127.0.0.1/255.255.255.0
read = system,call,log,verbose,agent,user,config,dtmf,reporting,cdr,dialplan
write = system,call,agent,user,config,command,reporting,originate
```

Finally you must edit ```http.conf``` and enable it by setting ```'enable'``` attibute to yes.

One could also setup bindaddr to explicitly define where the manager interface should bind to.

> Todo

* This version of Shift8 is considered an early version where many events returned by the remote asterisk might not be trapped properly. I have decided to make it public to get some assistance in
* I need to populate the examples that come with Shift8
* I have started building a GUI (web frontend) based on AJAX and Shift8, but since I am lousy on graphical design, If anyone is willing to assist please drop me a line
* Expand the documentation

> Changelog

* 0.1.3
Fixed bug with proxy() not returning correct error when no asterisk could be reached
Fixed a warning with an non existing variable

* 0.1.2
Fixed wrong parameter Date on Originate Action
Added urlencode() in all variables passed to AJAM
Removed a debug syslog message

* 0.1.1
Corrected a bug on proxy function that would cause the request to be sent twice on the remote asterisk server
Added support for saving and retrieving the last error message that has occurred in the communication with the remote asterisk (via ```setLastError()/getLastError()```)
Changed queueAddInterface to support member, penalty, and paused status

* 0.1
Initial release

> Examples

* <a href="https://github.com/twmobius/Shift8/wiki/How-to-perform-ChanSpy-from-Asterisk-Manager-using-Shift8">How to perform chanspy from asterisk manager using Shift8</a></li>
* <a href="https://github.com/twmobius/Shift8/wiki/Simple-Channel-Monitoring-via-Shift8">Simple Channel Monitoring via Shift8</a></li>

> Shift8 API

* https://github.com/twmobius/Shift8/tree/master/docs/api
