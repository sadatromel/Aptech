<?php

declare(strict_types=1);

namespace App\Http;

class Response
{
    private array $headers = [];
    private int $statusCode = 200;
    private string $content = '';

    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function json(array $data): self
    {
        $this->setHeader('Content-Type', 'application/json');
        $this->setContent(json_encode($data, JSON_PRETTY_PRINT));
        return $this;
    }

    public function view(string $template, array $data = []): self
    {
        $twig = \Core\Container::get(\Twig\Environment::class);
        $this->setContent($twig->render($template . '.twig', $data));
        return $this;
    }

    public function redirect(string $url): self
    {
        $this->setStatusCode(302);
        $this->setHeader('Location', $url);
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        
        if ($this->statusCode === 302 || $this->statusCode === 301) {
            exit;
        }
        
        echo $this->content;
    }
}
