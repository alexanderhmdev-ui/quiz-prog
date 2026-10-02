<?php
class Question
{
    public function __construct(
        public string $category,
        public string $text,
        public array $options,
        public string $difficulty = 'medium'
    ) {}

    public static function fromApi(array $data): self
    {
        return new self(
            (string)($data['category'] ?? 'General'),
            (string)($data['text'] ?? ''),
            (array)($data['options'] ?? []),
            (string)($data['difficulty'] ?? 'medium')
        );
    }
}
