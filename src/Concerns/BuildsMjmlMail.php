<?php

declare(strict_types=1);

namespace BBSLab\LaravelMjml\Concerns;

use BBSLab\LaravelMjml\Mjml\MjmlCompiler;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Illuminate\View\View as IlluminateView;
use RuntimeException;

trait BuildsMjmlMail
{
    /** @var view-string|null */
    protected ?string $mjml = null;

    protected ?string $mjmlContent = null;

    /**
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    public function mjml(string $view, array $data = []): static
    {
        $this->mjml = $view;
        $this->viewData = array_merge($this->buildViewData(), $data);

        return $this;
    }

    public function mjmlContent(string $mjmlContent): static
    {
        $this->mjmlContent = $mjmlContent;

        return $this;
    }

    /**
     * @return array{html: HtmlString, text: HtmlString}
     */
    public function buildMjmlView(): array
    {
        $compiler = $this->makeMjmlCompiler();

        return [
            'html' => $compiler->renderHtml(),
            'text' => $compiler->renderText(),
        ];
    }

    /**
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    public function mjmlContentDefinition(string $view, array $data = []): Content
    {
        $built = $this->mjml($view, $data)->buildMjmlView();

        return new Content(
            html: $built['html']->toHtml(),
            text: $built['text']->toHtml(),
        );
    }

    /**
     * @return array<string, HtmlString>|string
     */
    protected function buildView(): array|string
    {
        if ($this->mjml !== null || $this->mjmlContent !== null) {
            return $this->buildMjmlView();
        }

        return parent::buildView();
    }

    protected function makeMjmlCompiler(): MjmlCompiler
    {
        if ($this->mjml !== null) {
            $view = View::make($this->mjml, $this->buildViewData());

            if (! $view instanceof IlluminateView) {
                throw new RuntimeException('MJML views must be renderable Blade views.');
            }

            return new MjmlCompiler(
                $view,
                $this->buildViewData(),
            );
        }

        return new MjmlCompiler(
            (string) $this->mjmlContent,
            $this->buildViewData(),
        );
    }
}
