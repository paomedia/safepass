# safepass
- Simple command line, self-contained password manager.
- Should work on most Linux/Unix based systems.
- Require php-cli (PHP 5 >= 5.3.0) e.g. $ apt install php-cli

## Features
- AES 256 encrypted
- Customizable strong password generation
- Passwords and Data are embeded in source code (weird ?)
- You can have multiple safes if you change filename
- ...

## Setup

- Download safepass.php
- Rename it if you want
- Chmod it +x to make it executable
- Put it in your ~/bin directory, on a usb stick or anywhere you want
- Masterkey (main password) will be set on your first use

### Example for command line lovers

```
$ wget https://git.io/JDGbs -O mypersonalsafe
$ chmod +x mypersonalsafe
$ ./mypersonalsafe reset
```
 
## Main commands

- safepass.php add
- safepass.php show
- safepass.php delete


## Usage

```
USAGE
  safepass.php COMMAND [OPTION]

COMMANDS
  add                add new account
  delete SERVICE     delete account by SERVICE name
  dump [--decrypt]   display database as json
  genpasswd          display a random generated password
  help               display this help and exit
  reset              erase database, reinit
  savemk LOCATION    save master key on disk or ram for future use
  show [KEYWORD]     display accounts that match KEYWORD
  version            output version information and exit

SAVEMK USAGE
  safepass.php savemk [-r|-h]

  -r, --ram          save masterkey temporarly in ram
                     mk will be in /run/user/1000/safepass.php.mk
  -h, --home         save masterkey permanently
                     mk will be in /home/me/.safepass.php.mk

GENPASSWD USAGE
  safepass.php genpasswd [len] [lc] [uc] [spec] [num]

  len                password length (default=16)
  lc                 minimum lowercase chars (default=6)
  uc                 exact uppercase chars (default=6)
  spec               exact special chars (default=2)
  num                exact numerical chars (default=2)

```
