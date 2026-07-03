<?php

namespace App\Application\DTOs;

readonly class CreateTaskDTO
{
    public function __construct(
        public string $title,
    ) {}
}
