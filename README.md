# Warden

[![Build Status](https://github.com/Innmind/Warden/workflows/CI/badge.svg?branch=master)](https://github.com/Innmind/Warden/actions?query=workflow%3ACI)
[![codecov](https://codecov.io/gh/Innmind/Warden/branch/develop/graph/badge.svg)](https://codecov.io/gh/Innmind/Warden)
[![Type Coverage](https://shepherd.dev/github/Innmind/Warden/coverage.svg)](https://shepherd.dev/github/Innmind/Warden)

Tool to manage ssh connections for a server

## Installation

```sh
composer global require innmind/warden
```

## Usage

```sh
warden wakeup
```

This will modify the ssh configuration to only allow connections via ssh key.

```sh
warden grant [user]
```

This will add the ssh keys the user added on his github account to `.ssh/authorized_keys`

```sh
warden lock
```

This will stop the ssh service, **to be used carefully** as you won't be able to connect to your server afterward.
