# lbconf
Command line utility for reading and writing JSON configuration files

## Installation
Install using composer:
```bash
$ composer require learningbird/lbconf
```

## Setup
Create an `.lbconf` meta-configuration file in your project root:

Example:
```json
{
    "read": [
        "configurations/default.json"
    ],
    "write": "configurations/override.json"
}
```
File paths are relative to the location of the meta-config file.

The files in BOTH the `read` and `write` sections are read and merged, and the file in the `write` section is written to when using the `set` command.

### Dynamic Configuration
For more dynamic configurations, an `.lbconf.php` file can be used. This file must return an array that has the same format as the `.lbconf` JSON file.

```php
return [
    'read'  => glob('configurations/default/*.json),
    'write' => 'configurations/override.json',
];
```

## Usage
The general usage pattern is:
```bash
lbconf <action> <key> [value]
```

Where `<action>` is one of: `-g|--get`, `-s|--set`, `-d|--del`, `-k|--keys`.
If only a key is provided, the action is assumed to be `--get`, and can be omitted. If both a key and a value are provided, the action is assumed to be `--set`, and can be omitted.

Example:
```bash
$ lbconf --get database.host # Outputs "localhost"
$ lbconf database.host # Identical to above

$ lbconf --set database.host 127.0.0.1 # Sets database.host to  "127.0.0.1"
$ lbconf database.host 127.0.0.1 # Identical to above
```

### `-g|--get`
Retrieve configuration values.

```bash
$ lbconf --get database
$ lbconf -g database # Short form
$ lbconf database # Implicit "--get" form

# Output:
# {
#     "host": "localhost,
#     "port": 3306,
#     "username": "prod-user"
# }
```

Traverse objects by passing dot-separated keys:
```bash
$ lbconf --get database.host # Outputs "localhost"
```

### `-s|--set`
Set configuration values. Values will be written to the file specified by the `write` key in the `.lbconf` meta-configuration file.

```bash
$ lbconf --set database.username dev-user
$ lbconf -s database.username dev-user # Short form
$ lbconf database.username dev-user # Implicit "--set" form
```

Types will be inferred, unless explicitly specified:
```bash
$ lbconf --set database.port 3306 # Value is cast to int
$ lbconf --set database.port 3306 --type string # Value remains as string
```

### `-d|--del`
Delete overriding configuration values:

```bash
$ lbconf --del database.username
$ lbconf -d database.username # Short form
```

Note that the key must exist in the `write` file for the deletion to be permitted. There is no way to delete a key that only exists in the `read` file.
The only alternative is to set it to null, or some such value.

### `-k|--keys`
Retrieve configuration value keys:

```bash
$ lbconf --keys database
$ lbconf -k database # Short form

# Output:
# [
#     "host",
#     "port",
#     "username"
# ]
```

## Misc
### Keys beginning with dashes
By default, a command argument that begins with a `-` is interpreted as an option. To avoid this behaviour, if a configuration key begins with a `-`, you can use `--` to separate the command options from the arguments:

```bash
$ lbconf --get -- --get # Outputs value for key "--get"
$ lbconf --set -- --key value # Sets the value for "--key" to "value"
```