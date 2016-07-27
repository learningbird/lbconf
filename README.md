# lbconf
Command line utility for reading and writing JSON configuration files

## Installation
Install using composer:
```bash
composer require learningbird/lbconf
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

## Usage
### `get`
Retrieve configuration values:

```bash
lbconf get database
# Outputs:
# {
#     "host": "localhost,
#     "port": 3306,
#     "username": "prod-user",
# }
```

Traverse objects by passing dot-separated keys:
```bash
lbconf get database.host # Outputs "localhost"
```

### `set`
Set configuration values. Values will be written to the file specified by the `write` key in the `.lbconf` meta-configuration file.

```bash
lbconf set database.username dev-user
```

Types will be inferred, unless explicitly specified:
```bash
lbconf set database.port 3306 # Value is cast to int
lbconf set database.port 3306 --type string # Value remains as string
```

### `del`
Delete overriding configuration values:

```bash
lbconf del database.username
```

Note that the key must exist in the `write` file for the deletion to be permitted. There is no way to delete a key that only exists in the `read` file.
The only alternative is to set it to null, or some such value.

### `keys`
Retrieve configuration value keys:

```bash
lbconf keys database
# Outputs:
# [
#     "host",
#     "port",
#     "username",
# ]
```
