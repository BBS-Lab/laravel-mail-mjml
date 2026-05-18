<?php

declare(strict_types=1);

namespace BBSLab\LaravelMjml\Mjml;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;
use RuntimeException;
use Soundasleep\Html2Text;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

class MjmlCompiler
{
    protected string $mjmlContent;

    protected string $compiledMjmlPath;

    protected string $compiledHtmlPath;

    protected ?View $view = null;

    /** @var array<string, mixed> */
    protected array $data;

    protected ?string $basePath;

    protected ?MjmlIncludeResolver $includeResolver;

    /** @var callable(string): void|null */
    protected mixed $processRunner;

    protected ?HtmlString $cachedHtml = null;

    /**
     * @param  array<string, mixed>  $data
     * @param  callable(string): void|null  $processRunner
     */
    public function __construct(
        string|View $mjmlViewOrMjml,
        array $data = [],
        ?string $basePath = null,
        ?MjmlIncludeResolver $includeResolver = null,
        mixed $processRunner = null,
    ) {
        $this->data = $data;
        $this->basePath = $basePath;
        $this->includeResolver = $includeResolver;
        $this->processRunner = $processRunner;

        if ($mjmlViewOrMjml instanceof View) {
            $this->view = $mjmlViewOrMjml;
            $this->compiledMjmlPath = $this->compiledStoragePath(hash('sha256', json_encode([
                'path' => $this->view->getPath(),
                'data' => $this->view->getData(),
            ], JSON_THROW_ON_ERROR)).'.mjml.php');
        } else {
            $this->mjmlContent = $mjmlViewOrMjml;
            $this->compiledMjmlPath = $this->compiledStoragePath(hash('sha256', $mjmlViewOrMjml).'.mjml.php');
        }
    }

    public function renderHtml(): HtmlString
    {
        if ($this->cachedHtml instanceof HtmlString) {
            return $this->cachedHtml;
        }

        $this->prepareMjmlContent();

        File::put($this->compiledMjmlPath, $this->mjmlContent);

        $contentChecksum = hash('sha256', $this->mjmlContent);
        $this->compiledHtmlPath = $this->compiledStoragePath($contentChecksum.'.html');

        if (! File::exists($this->compiledHtmlPath)) {
            $this->runMjmlProcess();
        }

        $html = File::get($this->compiledHtmlPath);

        if (config('mjml.rerender_blade_after_compile', false)) {
            $html = Blade::render($html, $this->data);
        }

        $this->cachedHtml = new HtmlString($html);

        return $this->cachedHtml;
    }

    /**
     * @throws Throwable
     */
    public function renderText(): HtmlString
    {
        $html = $this->renderHtml()->toHtml();

        $text = html_entity_decode(
            preg_replace(
                "/[\r\n]{2,}/",
                "\n\n",
                Html2Text::convert($html, ['ignore_errors' => true]),
            ) ?: '',
            ENT_QUOTES,
            'UTF-8',
        );

        return new HtmlString($text);
    }

    public function buildCommandLine(): string
    {
        $binary = config('mjml.auto_detect_path', true)
            ? $this->detectBinaryPath()
            : (string) config('mjml.path_to_binary');

        $configFilePath = $this->view
            ? '--config.filePath='.escapeshellarg(dirname($this->view->getPath()))
            : '';

        $node = config('mjml.node_path', 'node');

        return implode(' ', array_filter([
            filled($node) ? $node : null,
            escapeshellarg($binary),
            escapeshellarg($this->compiledMjmlPath),
            $configFilePath,
            '-o',
            escapeshellarg($this->compiledHtmlPath),
        ]));
    }

    public function detectBinaryPath(): string
    {
        return base_path('node_modules/.bin/mjml');
    }

    protected function prepareMjmlContent(): void
    {
        if ($this->view instanceof View) {
            $viewPath = $this->view->getPath();

            if ($viewPath === '') {
                throw new RuntimeException('MJML views must have a resolvable filesystem path.');
            }

            $this->mjmlContent = (string) file_get_contents($viewPath);
            $basePath = $this->basePath ?? dirname($viewPath);
        } else {
            $basePath = $this->basePath ?? resource_path('views');
        }

        if (config('mjml.process_includes_with_blade', true)) {
            $resolver = $this->includeResolver ?? new MjmlIncludeResolver;

            $this->mjmlContent = $resolver->resolve($this->mjmlContent, $basePath);
            $this->mjmlContent = Blade::render($this->mjmlContent, $this->data);
        } elseif ($this->view instanceof View) {
            $this->mjmlContent = $this->view->render();
        } else {
            $this->mjmlContent = Blade::render($this->mjmlContent, $this->data);
        }
    }

    protected function runMjmlProcess(): void
    {
        $command = $this->buildCommandLine();

        if (is_callable($this->processRunner)) {
            ($this->processRunner)($command);

            return;
        }

        $process = Process::fromShellCommandline($command);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    protected function compiledStoragePath(string $filename): string
    {
        $directory = rtrim((string) config('view.compiled'), DIRECTORY_SEPARATOR.'/');
        $basename = ltrim($filename, DIRECTORY_SEPARATOR.'/');

        return $directory.DIRECTORY_SEPARATOR.$basename;
    }
}
