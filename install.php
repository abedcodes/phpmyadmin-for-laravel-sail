<?php

$cli = new CLI();

############### configs ################
define("PHPMYADMIN_VERSION", $cli->arguments['--version'] ?? '5.2.1');
define("PHPMYADMIN_PORT", $cli->arguments['--port'] ?? '8080');
const DOCKER_COMPOSE_FILE = './docker-compose.yml';
const SAIL_TRAIT_FILE = './vendor/laravel/sail/src/Console/Concerns/InteractsWithDockerComposeServices.php';
########################################

########### execution lines ############

if(in_array('--restore', $argv))
{
    try {
        if(TextProcessor::doesDockerComposeContainPhpMyAdmin()) {
            Backup::restoreDockerComposeFile();
        }
        else {
            Backup::restoreInteractsWithDockerComposeServicesFile();
            Backup::removePhpMyAdminStub();
        }
    } catch (Exception $e)
    {
        echo $e->getMessage() . PHP_EOL;
    }
    exit;
}

$phpMyAdmin = new phpMyAdmin;

if (in_array('--add', $argv)) {
    $phpMyAdmin->add();
}
else {
    $phpMyAdmin->inject();
}
########################################

class phpMyAdmin
{
    protected string $phpMyAdminService;

    public function __construct()
    {
        $phpMyAdminVersion = get_defined_constants()['PHPMYADMIN_VERSION'];
        $phpMyAdminPort = get_defined_constants()['PHPMYADMIN_PORT'];

        $this->phpMyAdminService =
        <<<PHPMYADMIN
        phpmyadmin:
            image: 'phpmyadmin:$phpMyAdminVersion'
            ports:
                - "$phpMyAdminPort:80"
            environment:
                PMA_HOST: mysql
            networks:
                - sail
            depends_on:
                - mysql
        PHPMYADMIN;
    }

    /**
     * indents all lines of $phpMyAdminService for injecting into docker-compose.yml file
     *
     * @return string
     */
    public function indentPhpMyAdminServiceLines(): string
    {
        $phpMyAdminServiceLines = explode("\n", $this->phpMyAdminService);
        return implode("\n", TextProcessor::addIndentationToLines($phpMyAdminServiceLines));
    }

    /**
     * injects phpmyadmin service directly to docker-compose.yml
     *
     * @return void
     */
    public function inject(): void
    {
        try {
            $lines = TextProcessor::readDockerComposeLines();
        } catch (Exception $e)
        {
            echo $e->getMessage() . PHP_EOL;
            exit;
        }

        $lineNumber = TextProcessor::findLastLineNumberOfServices($lines);
        $phpMyAdminService = $this->indentPhpMyAdminServiceLines();

        $lines[$lineNumber - 1] = $lines[$lineNumber - 1] . "\n$phpMyAdminService";
        $dockerComposeFile = get_defined_constants()['DOCKER_COMPOSE_FILE'];
        Backup::backupDockerComposeFile();

        print file_put_contents($dockerComposeFile, implode("\n", $lines)) 
            ? 'DONE. phpmyadmin service was injected to docker-compose.yml'.PHP_EOL
            : 'FAILED! couldn\'t inject phpmyadmin service to docker-compose.yml'.PHP_EOL;
    }

    /**
     * adds phpmyadmin to sail's services
     *
     * @return void
     */
    public function add(): void
    {
        try {
            $lines = TextProcessor::readInteractsWithDockerComposeServicesLines();
        } catch (Exception $e)
        {
            echo $e->getMessage() . PHP_EOL;
            exit;
        }

        $lineNumber = TextProcessor::findLastServiceLineNumber($lines);
        $lines[$lineNumber - 1] = $lines[$lineNumber - 1] . "\n\t\t'phpmyadmin',";
        $sailTraitFile = get_defined_constants()['SAIL_TRAIT_FILE'];
        Backup::backupInteractsWithDockerComposeServicesFile();

        print file_put_contents($sailTraitFile, implode("\n", $lines))
            ? 'DONE. phpmyadmin service was added to list of sail\'s services'.PHP_EOL
            : 'FAILED! couldn\'t add phpmyadmin to list of sail\'s services'.PHP_EOL;
        
        $this->publishStub();
    }

    /**
     * creates phpmyadmin.stub file in sails stub directory
     *
     * @return void
     */
    private function publishStub(): void
    {
        $sailStubsPath = dirname(get_defined_constants()['SAIL_TRAIT_FILE'], 4) . '/stubs';
        file_put_contents("$sailStubsPath/phpmyadmin.stub", $this->phpMyAdminService);
    }
}

class TextProcessor
{
    /**
     * returns first service line number in $services array
     * within InteractsWithDockerComposeServices.php file
     *
     * @param array $lines
     * @return int
     */
    public static function findFirstServiceLineNumber(array $lines): int
    {
        return array_search('protected $services = [', self::removeLinesIndentation($lines)) + 2;
    }

    /**
     * returns last service line number in $services array
     * within InteractsWithDockerComposeServices.php file
     *
     * @param array $lines
     * @return int
     */
    public static function findLastServiceLineNumber(array $lines): int
    {
        return array_search('];', self::removeLinesIndentation($lines));
    }

    /**
     * @param array $lines
     * @return array
     */
    public static function removeLinesIndentation(array $lines): array
    {
        return array_map(fn($line) => trim($line), $lines);
    }

    /**
     * YAML syntax doesn't accept \t for indentation so 4 spaces used instead
     *
     * @param array $lines
     * @return array
     */
    public static function addIndentationToLines(array $lines): array
    {
        return array_map(fn($line) => "    $line", $lines);
    }

    /**
     * returns last line number of services object in docker-compose.yml
     *
     * @param array $lines
     * @return int
     */
    public static function findLastLineNumberOfServices(array $lines): int
    {
        return array_search('networks:', $lines);
    }

    /**
     * reads all lines of docker-compose.yml file into an array & returns it
     *
     * @return array
     * @throws Exception
     */
    public static function readDockerComposeLines(): array
    {
        $dockerComposeFile = get_defined_constants()['DOCKER_COMPOSE_FILE'];
        if (!is_file($dockerComposeFile) || !is_readable($dockerComposeFile))
            throw new Exception("Failed! can't read docker-compose.yml lines or file does not exist");

        return file($dockerComposeFile, FILE_IGNORE_NEW_LINES);
    }

    /**
     * reads all lines of InteractsWithDockerComposeServices.php file into an array & returns it
     *
     * @return array
     * @throws Exception
     */
    public static function readInteractsWithDockerComposeServicesLines(): array
    {
        $sailTraitFile = get_defined_constants()['SAIL_TRAIT_FILE'];
        if (!is_file($sailTraitFile) || !is_readable($sailTraitFile))
            throw new Exception("Failed! can't read InteractsWithDockerComposeServices.php lines or file does not exist");

        return file($sailTraitFile, FILE_IGNORE_NEW_LINES);
    }

    /**
     * @throws Exception
     */
    public static function doesDockerComposeContainPhpMyAdmin(): bool
    {
        $lines = self::readDockerComposeLines();
        return in_array('    phpmyadmin:', $lines);
    }
}

class Backup
{
    public static function backupDockerComposeFile(): void
    {
        $dockerComposeFile = get_defined_constants()['DOCKER_COMPOSE_FILE'];
        $dockerComposeDir = dirname($dockerComposeFile);
        copy($dockerComposeFile, "$dockerComposeDir/docker-compose.backup");
    }

    public static function backupInteractsWithDockerComposeServicesFile(): void
    {
        $sailTraitFile = get_defined_constants()['SAIL_TRAIT_FILE'];
        $sailTraitDir = dirname($sailTraitFile);
        copy($sailTraitFile, "$sailTraitDir/InteractsWithDockerComposeServices.backup");
    }

    /**
     * @throws Exception
     */
    public static function restoreDockerComposeFile(): void
    {
        $dockerComposeFile = get_defined_constants()['DOCKER_COMPOSE_FILE'];
        $dockerComposeDir = dirname($dockerComposeFile);
        $dockerComposeBackupFile = "$dockerComposeDir/docker-compose.backup";

        if (is_file($dockerComposeBackupFile) && is_readable($dockerComposeBackupFile))
            copy($dockerComposeBackupFile, $dockerComposeFile);
        else
            throw new Exception("Restoration Failed! docker-compose.backup doesn't exist or isn't readable");
    }

    /**
     * @throws Exception
     */
    public static function restoreInteractsWithDockerComposeServicesFile(): void
    {
        $sailTraitFile = get_defined_constants()['SAIL_TRAIT_FILE'];
        $sailTraitDir = dirname($sailTraitFile);
        $sailTraitBackupFile = "$sailTraitDir/InteractsWithDockerComposeServices.backup";

        if (is_file($sailTraitBackupFile) && is_readable($sailTraitBackupFile))
            copy($sailTraitBackupFile, $sailTraitFile);
        else
            throw new Exception("Restoration Failed! InteractsWithDockerComposeServices.backup doesn't exist or isn't readable");
    }

    public static function removePhpMyAdminStub(): void
    {
        $sailStubsPath = dirname(get_defined_constants()['SAIL_TRAIT_FILE'], 4) . '/stubs';
        unlink("$sailStubsPath/phpmyadmin.stub");
    }
}

class CLI
{
    public array $arguments;

    public function __construct()
    {
        global $argv;
        $pairArguments = array_filter($argv, fn($arg) => strpos($arg, '='));
        $query = implode('&', $pairArguments);
        parse_str($query, $params);
        $this->arguments = $params;
    }
}
