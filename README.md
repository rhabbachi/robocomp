# robocomp-dkan
Docker compose project with a helper [Robo](http://robo.li/) script to setup a full DKAN stack on Docker.
## Requirements
* PHP 7.1

## How to

```sh
$ cd <robocomp project directory>
```

**Install Composer**

```sh
$ composer install
```

**Prepare app.yml**
```sh
$ vendor/bin/robo config:init
$ <edit app.yml with appropriate settings>.
$ vendor/bin/robo config:generate
```

**Build, Start, and Setup machines**
```sh
$ vendor/bin/robo app:build
$ vendor/bin/robo app:start
$ vendor/bin/robo app:setup
```
