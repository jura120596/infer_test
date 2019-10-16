<?php

use App\Jobs\AnalyzeJob;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * Class Php
 * @package App\Analyzers
 */
abstract class Php
{
    public const OUT_FILE_EXTENSION = '.out';
    public const RESULT_FILE = 'result.json';
    public const NPM_LINTERS_PATH = '/var/www/node_modules/.bin/';
    public const INFER_LINTERS_PATH = '/infer/bin/';
    protected $projectAnalyzerDirectory;
    protected $configFileName;
    protected $linterName;
    protected $linterFilesListFileName = 'files_to_analyze.txt';
    public const OUT_FILTER_REGEX = '^$';
    /**
     * @var AnalyzeJob $job
     */
    protected $job;

    /**
     * AbstractLanguageAnalyzer constructor.
     * @param AnalyzeJob $analyzeJob
     */
    public function __construct(AnalyzeJob $analyzeJob)
    {
        $this->job = $analyzeJob;
        $this->projectAnalyzerDirectory = $this->job->getIdentifier() . DIRECTORY_SEPARATOR .
            AnalyzeJob::LANG_DIRS_PREFIX . $this->linterName . DIRECTORY_SEPARATOR;
    }

    /**
     * Linter initialization actions, creating configs
     */
    public function lintInit() : void
    {
        $disk = Storage::disk(AnalyzeJob::STORAGE_DISK);
        chdir($disk->path($this->getDirectory()));
        Artisan::call('linter:config ' . $this->linterName . ' ' . $this->configFileName);
    }

    /**
     * Analyze code in file
     * @param array $files
     * @param string $lang
     */
    public function linterRun(array $files = [], string $lang = ''): void
    {
        $this->lintInit();
        $linterCommandOut = shell_exec($this->getShellCommand());
        $this->toJsonFile($linterCommandOut);
    }

    /**
     * Get main shell command
     * @return string
     */
    abstract public function getShellCommand(): string;

    /**
     * Convert linter command results to array
     * @param string $linterCommandOut
     * @return array
     */
    public function toArray(string $linterCommandOut) : array
    {
        $result = [];
        foreach (preg_split('/\n/', $linterCommandOut) as $line) {
            if (preg_match(static::OUT_FILTER_REGEX, $line, $matches)) {
                $result[$matches[1]] = Arr::get($result, $matches[1], 0) + 1;
            }
        }
        return $result;
    }

    /**
     * Save lint results formatted to json
     * @param string $linterCommandOut
     * @param string $fileName
     * @return string
     */
    public function toJsonFile(string $linterCommandOut, string $fileName = ''): string
    {
        $file = AnalyzeJob::RESULTS_DIR_PREFIX . $this->job->getIdentifier() . DIRECTORY_SEPARATOR .
            ($fileName ?:  static::RESULT_FILE);
        $json = json_encode($this->toArray($linterCommandOut));
        Storage::disk(AnalyzeJob::STORAGE_DISK)->put($file, $json);
        return $json;
    }

    /**
     * Get directory with analyzable files
     * @return string
     */
    public function getDirectory() : string
    {
        return $this->projectAnalyzerDirectory;
    }

    /**
     * Getter
     * @return string
     */
    public function getConfigFileName() : string
    {
        return $this->configFileName;
    }

    /**
     * @param array $files
     */
    public function prepareFileWithFilesToLinting(array $files) : void
    {
        file_put_contents($fn = $this->linterFilesListFileName, implode("\n", $files));
    }
}