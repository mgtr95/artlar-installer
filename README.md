# Artlar Installer

CLI to scaffold apps from [`mgtr95/artlar-boilerplate`](https://github.com/mgtr95/artlar-boilerplate).

## Requirements

- PHP 8.2+
- [Composer](https://getcomposer.org/)
- For Docker boot: Docker + Make (optional)

## Install

```bash
composer global require mgtr95/artlar-installer
```

Ensure Composer's global bin directory is on your `PATH` (often `~/.composer/vendor/bin` or `~/.config/composer/vendor/bin`).

Until a stable tag exists:

```bash
composer global require mgtr95/artlar-installer:dev-main
```

## Usage

```bash
artlar new my-app
```

Options:

| Option | Meaning |
|--------|---------|
| `--docker` | Run `make up` after install |
| `--no-docker` | Skip Docker |
| `--git` | `git init` + initial commit |
| `--dev` | Use `dev-main` of the boilerplate |
| `--from-git` | Always pull the boilerplate via GitHub VCS |
| `-f`, `--force` | Overwrite existing directory |

Without the CLI:

```bash
composer create-project mgtr95/artlar-boilerplate my-app
```

## Local development of this installer

```bash
cd artlar-installer
composer install
./bin/artlar new /tmp/artlar-demo --no-docker
```

## Packagist

Packages:

1. `mgtr95/artlar-boilerplate` (`type: project`)
2. `mgtr95/artlar-installer` (this CLI)

Tag releases (`v1.0.0`, …) on both repos so Composer can install without `--stability=dev`. The CLI auto-detects Packagist and only falls back to the GitHub VCS URL when needed.
