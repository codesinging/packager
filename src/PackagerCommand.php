<?php
/**
 * Author:  CodeSinging (The code is singing)
 * Email:   codesinging@gmail.com
 * Github:  https://github.com/codesinging
 * Time:    2020-05-21 09:23:23
 */

namespace CodeSinging\Packager;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;

class PackagerCommand extends Command
{
    /**
     * @var array Package information
     */
    protected $config = [
        'directory' => '',
        'authorName' => '',
        'authorEmail' => '',
        'package' => '',
        'name' => '',
        'vendor' => 'codesinging',
        'namespace' => '',
        'description' => '',
        'license' => 'MIT',
        'phpunit' => false,
    ];

    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * @var string
     */
    protected $stubsDirectory;

    /**
     * @var string
     */
    protected $packageDirectory;

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('build');
        $this->setDescription('Build package');
        $this->addArgument('directory', InputArgument::OPTIONAL, 'The project directory');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->fs = new Filesystem();
        $this->stubsDirectory = __DIR__ . '/../stubs/';

        $this->config['directory'] = $input->getArgument('directory');
        $this->packageDirectory = './' . $this->config['directory'] . '/';

        $git = $this->getGitConfig();
        $helper = $this->getHelper('question');

        $vendor = $this->config['vendor'] . '/' . $this->config['directory'];
        $question = new Question("Name of package [<fg=yellow>{$vendor}</fg=yellow>]: ", $vendor);
        $question->setValidator(function ($value) {
            if (empty(trim($value))) {
                throw new \Exception('The name of package can not be empty');
            }
            if (!preg_match('/[a-z0-9\-_]+\/[a-z0-9\-_]+/', $value)) {
                throw new \Exception('The name of package is invalid');
            }

            return $value;
        });
        $question->setMaxAttempts(5);

        // package: vendor/name
        $this->config['package'] = $helper->ask($input, $output, $question);
        $namespace = implode('\\', array_map([$this, 'studlyCase'], explode('/', $this->config['package'])));

        // namespace: Vendor\Name
        $question = new Question("Namespace of package [<fg=yellow>{$namespace}</fg=yellow>]: ", $namespace);
        $this->config['namespace'] = $helper->ask($input, $output, $question);

        // vendor
        $this->config['vendor'] = strstr($this->config['package'], '/', true);
        $this->config['name'] = substr($this->config['package'], strlen($this->config['vendor']) + 1);

        // description
        $question = new Question('Description of package: ', $this->config['name']);
        $this->config['description'] = $helper->ask($input, $output, $question);

        // author name
        $question = new Question(sprintf('Author name of package [<fg=yellow>%s</fg=yellow>]: ', $git['user.name'] ?: $this->config['vendor']), $git['user.name'] ?: $this->config['vendor']);
        $this->config['authorName'] = $helper->ask($input, $output, $question);

        // author email
        if (empty($git['user.email'])) {
            $question = new Question('Author email of package: ');
        } else {
            $question = new Question(sprintf('Author email of package [<fg=yellow>%s</fg=yellow>]: ', $git['user.email']), $git['user.email']);
        }
        $this->config['authorEmail'] = $helper->ask($input, $output, $question);

        // license
        $question = new Question(sprintf('License of package [<fg=yellow>%s</fg=yellow>]: ', $this->config['license']), $this->config['license']);
        $this->config['license'] = $helper->ask($input, $output, $question);

        // test
        $question = new ConfirmationQuestion('Do you want use phpunit for this package? [<fg=yellow>Y/n</fg=yellow>]: ', 'yes');
        $this->config['phpunit'] = $helper->ask($input, $output, $question);

        $this->createPackage();
        $this->initComposer();
        $this->setNamespace();

        $output->writeln(sprintf('<info>Package %s created in directory: </info><comment>%s</comment>', $this->config['package'], $this->config['directory']));

        return 0;
    }

    /**
     * Create package.
     */
    protected function createPackage()
    {
        $this->fs->mkdir($this->packageDirectory . 'src/', 0755);
        $this->fs->touch($this->packageDirectory . 'src/.gitkeep');

        $this->copyFile('.gitattributes');
        $this->copyFile('.gitignore');
        $this->copyFile('.editorconfig');
        $this->copyFile('README.md', null, [
            '__TITLE__' => $this->studlyCase($this->config['name']),
            '__NAME__' => $this->config['name'],
            '__DESCRIPTION__' => $this->config['description'],
            '__PACKAGE__' => $this->config['package'],
            '__VENDOR__' => $this->config['vendor'],
            '__LICENSE__' => $this->config['license'],
        ]);

        if ($this->config['phpunit']) {
            $this->fs->dumpFile($this->packageDirectory . 'tests/.gitkeep', '');
            $this->copyFile('phpunit.xml.dist');
        }
    }

    /**
     * Initialize the composer.json file.
     */
    protected function initComposer()
    {
        $author = empty($this->config['authorEmail'])
            ? ''
            : sprintf('--author "%s <%s>"', $this->config['authorName'] ?: 'author name', $this->config['authorEmail'] ?: 'author email');

        exec(sprintf(
            'composer init --no-interaction --name "%s" %s --description "%s" --license %s --working-dir %s',
            $this->config['package'],
            $author,
            $this->config['description'],
            $this->config['license'],
            $this->packageDirectory
        ));
    }

    /**
     *
     */
    protected function setNamespace()
    {
        $file = $this->packageDirectory . 'composer.json';
        $data = json_decode(file_get_contents($file), true);

        $data['require'] = [
            "php" => ">7.1"
        ];

        $data['autoload'] = [
            'psr-4' => [
                $this->config['namespace'] . '\\' => 'src',
            ],
        ];

        if ($this->config['phpunit']) {
            $data['autoload-dev'] = [
                'psr-4' => [
                    $this->config['namespace'] . '\\Tests\\' => 'tests',
                ],
            ];
        }

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Get global configuration of git.
     * @return array
     */
    protected function getGitConfig()
    {
        try {
            $config = [];
            $segments = preg_split("/\n[\r]?/", trim(shell_exec('git config --list --global')));
            foreach ($segments as $segment) {
                [$key, $value] = array_pad(explode('=', $segment), 2, null);
                $config[$key] = $value;
            }
            return $config;
        } catch (\Exception $exception) {
            return [];
        }
    }

    /**
     * @param string $string
     * @return string
     */
    protected function studlyCase(string $string)
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $string)));
    }

    /**
     * @param string $src
     * @param null|string $dst
     * @param array $replaces
     */
    protected function copyFile($src, $dst = null, array $replaces = [])
    {
        $dstFile = $this->packageDirectory . ($dst ?: $src);
        $srcFile = $this->stubsDirectory . $src;

        $content = file_get_contents($srcFile);
        $content = str_replace(array_keys($replaces), array_values($replaces), $content);

        $this->fs->dumpFile($dstFile, $content);
    }
}