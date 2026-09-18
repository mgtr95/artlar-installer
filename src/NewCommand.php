<?php

namespace Artlar\Installer\Console;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class NewCommand extends Command
{
    /**
     * Packagist / Composer package name of the application template.
     */
    private const TEMPLATE_PACKAGE = 'mgtr95/artlar-boilerplate';

    /**
     * Git repository used when the package is not yet on Packagist.
     */
    private const TEMPLATE_REPOSITORY = 'https://github.com/mgtr95/artlar-boilerplate.git';

    protected function configure(): void
    {
        $this
            ->setName('new')
            ->setDescription('Create a new Artlar application')
            ->addArgument('name', InputArgument::OPTIONAL)
            ->addOption('dev', null, InputOption::VALUE_NONE, 'Install the latest development branch (dev-main)')
            ->addOption('git', null, InputOption::VALUE_NONE, 'Initialize a Git repository')
            ->addOption('docker', null, InputOption::VALUE_NONE, 'Run make up after install')
            ->addOption('no-docker', null, InputOption::VALUE_NONE, 'Skip the Docker prompt and do not run make up')
            ->addOption('from-git', null, InputOption::VALUE_NONE, 'Force create-project via the GitHub VCS repository')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite the target directory if it exists');
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        $output->writeln('');
        $output->writeln('  <fg=cyan>Artlar</> — Laravel + React/Inertia starter');
        $output->writeln('');

        if (! $input->getArgument('name')) {
            $input->setArgument('name', text(
                label: 'What is the name of your project?',
                placeholder: 'E.g. my-app',
                required: 'The project name is required.',
                validate: function (string $value) use ($input): ?string {
                    if (preg_match('/[^\pL\pN\-_.]/u', $value) !== 0) {
                        return 'The name may only contain letters, numbers, dashes, underscores, and periods.';
                    }

                    if ($input->getOption('force') !== true) {
                        try {
                            $this->verifyApplicationDoesntExist($this->getInstallationDirectory($value));
                        } catch (RuntimeException) {
                            return 'Application already exists.';
                        }
                    }

                    return null;
                },
            ));
        }

        if ($input->getOption('force') !== true) {
            $this->verifyApplicationDoesntExist(
                $this->getInstallationDirectory((string) $input->getArgument('name'))
            );
        }

        if (! $input->getOption('docker') && ! $input->getOption('no-docker')) {
            if (! $input->isInteractive()) {
                $input->setOption('docker', true);
            } else {
                $choice = select(
                    label: 'How do you want to boot the app after install?',
                    options: [
                        'docker' => 'Docker (make up) — recommended',
                        'skip' => 'Skip — I will start services myself',
                    ],
                    default: 'docker',
                );

                if ($choice === 'docker') {
                    $input->setOption('docker', true);
                } else {
                    $input->setOption('no-docker', true);
                }
            }
        }

        if (! $input->getOption('git') && $this->gitIsAvailable() && $input->isInteractive()) {
            $input->setOption('git', confirm(
                label: 'Initialize a Git repository?',
                default: true,
            ));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = rtrim((string) $input->getArgument('name'), '/\\');
        $directory = $this->getInstallationDirectory($name);

        if (! $input->getOption('force')) {
            $this->verifyApplicationDoesntExist($directory);
        }

        if ($input->getOption('force') && $directory === '.') {
            throw new RuntimeException('Cannot use --force when installing into the current directory.');
        }

        $composer = $this->findComposer();
        $phpBinary = $this->phpBinary();
        $version = $input->getOption('dev') ? 'dev-main' : '';

        $createProject = sprintf(
            '%s create-project %s %s %s --remove-vcs --prefer-dist --no-scripts',
            $composer,
            self::TEMPLATE_PACKAGE,
            Process::escapeArgument($directory),
            $version !== '' ? Process::escapeArgument($version) : '',
        );

        if ($input->getOption('from-git') || ! $this->packageIsOnPackagist()) {
            $repository = json_encode([
                'type' => 'vcs',
                'url' => self::TEMPLATE_REPOSITORY,
            ], JSON_THROW_ON_ERROR);

            $createProject .= ' --stability=dev --repository='.Process::escapeArgument($repository);
        }

        $commands = [];

        if ($directory !== '.' && $input->getOption('force')) {
            $commands[] = PHP_OS_FAMILY === 'Windows'
                ? sprintf('(if exist %s rd /s /q %s)', Process::escapeArgument($directory), Process::escapeArgument($directory))
                : sprintf('rm -rf %s', Process::escapeArgument($directory));
        }

        $commands[] = trim(preg_replace('/\s+/', ' ', $createProject) ?? $createProject);
        $commands[] = sprintf('%s run post-root-package-install -d %s', $composer, Process::escapeArgument($directory));
        $commands[] = sprintf('%s %s key:generate --ansi', $phpBinary, Process::escapeArgument($directory.DIRECTORY_SEPARATOR.'artisan'));

        if (PHP_OS_FAMILY !== 'Windows') {
            $commands[] = sprintf('chmod 755 %s', Process::escapeArgument($directory.DIRECTORY_SEPARATOR.'artisan'));
        }

        $process = $this->runCommands($commands, $input, $output);

        if (! $process->isSuccessful()) {
            $output->writeln('<error>Installation failed.</error>');

            return self::FAILURE;
        }

        if ($input->getOption('git') && $this->gitIsAvailable() && $directory !== '.') {
            $this->runCommands([
                sprintf('git -C %s init -q', Process::escapeArgument($directory)),
                sprintf('git -C %s add -A', Process::escapeArgument($directory)),
                sprintf('git -C %s commit -q -m %s', Process::escapeArgument($directory), Process::escapeArgument('Initial commit from artlar new')),
            ], $input, $output);
        }

        if ($input->getOption('docker')) {
            if (! $this->commandExists('make') || ! $this->commandExists('docker')) {
                $output->writeln('<comment>Docker or make not found on PATH — skipped make up.</comment>');
                $output->writeln(sprintf('  cd %s && cp -n .env.example .env 2>/dev/null; make up', $directory));
            } else {
                $this->runCommands([
                    sprintf('make -C %s up', Process::escapeArgument($directory)),
                ], $input, $output);
            }
        }

        $output->writeln('');
        $output->writeln(sprintf('  <info>Application ready:</info> %s', $directory === '.' ? getcwd() : $directory));
        $output->writeln('');
        $output->writeln('  Next steps:');
        $output->writeln(sprintf('    cd %s', $name === '.' ? '.' : $name));

        if (! $input->getOption('docker')) {
            $output->writeln('    make up          # Docker stack (recommended)');
        }

        $output->writeln('    # App: http://localhost:8000');
        $output->writeln('');

        return self::SUCCESS;
    }

    private function getInstallationDirectory(string $name): string
    {
        return $name !== '.' ? getcwd().DIRECTORY_SEPARATOR.$name : '.';
    }

    private function verifyApplicationDoesntExist(string $directory): void
    {
        if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
            throw new RuntimeException('Application already exists!');
        }
    }

    private function findComposer(): string
    {
        $composerPath = getcwd().'/composer.phar';

        if (file_exists($composerPath)) {
            return Process::escapeArgument($this->phpBinary()).' '.Process::escapeArgument($composerPath);
        }

        return 'composer';
    }

    private function phpBinary(): string
    {
        if (defined('PHP_BINARY') && PHP_BINARY !== '') {
            return PHP_BINARY;
        }

        return 'php';
    }

    private function gitIsAvailable(): bool
    {
        return $this->commandExists('git');
    }

    private function commandExists(string $command): bool
    {
        $check = PHP_OS_FAMILY === 'Windows' ? 'where' : 'command -v';
        $process = Process::fromShellCommandline(sprintf('%s %s', $check, escapeshellarg($command)));
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Best-effort Packagist probe so create-project can skip the VCS repository override when published.
     */
    private function packageIsOnPackagist(): bool
    {
        $url = 'https://repo.packagist.org/p2/'.self::TEMPLATE_PACKAGE.'.json';
        $context = stream_context_create([
            'http' => [
                'timeout' => 3,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        return is_string($body) && str_contains($body, '"packages"');
    }

    /**
     * @param  list<string>  $commands
     */
    private function runCommands(array $commands, InputInterface $input, OutputInterface $output): Process
    {
        $process = Process::fromShellCommandline(implode(' && ', $commands), timeout: null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException) {
                $output->writeln('  <comment>Unable to attach TTY — running without live output.</comment>');
            }
        }

        $process->run(function (string $type, string $line) use ($output): void {
            $output->write($line);
        });

        return $process;
    }
}
