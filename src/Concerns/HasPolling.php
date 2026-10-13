<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Concerns;

use InvalidArgumentException;

trait HasPolling
{
    protected ?int $pollInterval = null;

    public function poll(int $seconds): self
    {
        if ($seconds < 1) {
            throw new InvalidArgumentException('The poll interval must be at least one second.');
        }

        $this->pollInterval = $seconds;

        return $this;
    }

    public function getPollInterval(): ?int
    {
        return $this->pollInterval;
    }

    /**
     * @return array{poll?: int}
     */
    protected function pollPayload(): array
    {
        if (is_null($this->pollInterval)) {
            return [];
        }

        return [
            'poll' => $this->pollInterval,
        ];
    }
}
