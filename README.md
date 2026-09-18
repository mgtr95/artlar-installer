# Artlar Installer

CLI to scaffold apps from [`mgtr95/art-lar-template`](https://github.com/mgtr95/art-lar-template).

## Requirements

- PHP 8.2+
- [Composer](https://getcomposer.org/)
- For Docker boot: Docker + Make (optional)

## Install

Until this package is on Packagist:

```bash
composer global config repositories.artlar-installer vcs https://github.com/mgtr95/artlar-installer.git
composer global require mgtr95/artlar-installer:dev-main
```

Once published on Packagist:

```bash
composer global require mgtr95/artlar-installer
```

Ensure Composer's global bin directory is on your `PATH` (often `~/.composer/vendor/bin` or `~/.config/composer/vendor/bin`).

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
| `--dev` | Use `dev-main` of the template |
| `--from-git` | Always pull the template via GitHub VCS |
| `-f`, `--force` | Overwrite existing directory |

Without the CLI:

```bash
composer create-project mgtr95/art-lar-template my-app --stability=dev \
  --repository='{"type":"vcs","url":"https://github.com/mgtr95/art-lar-template.git"}'
```

## Local development of this installer

```bash
cd artlar-installer
composer install
./bin/artlar new /tmp/artlar-demo --no-docker
```

## Packagist

Submit **both** packages when ready:

1. `mgtr95/art-lar-template` (this template / `type: project`)
2. `mgtr95/artlar-installer` (this CLI)

After that, `composer global require mgtr95/artlar-installer` and `composer create-project mgtr95/art-lar-template` work without VCS repository config. The CLI auto-detects Packagist and drops the Git override when the template is published.
