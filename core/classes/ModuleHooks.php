<?php

declare(strict_types=1);

/** Lightweight, request-local action/filter events; registrations owned by active modules. */
final class ModuleHooks
{
    private array $actions = [];
    private array $filters = [];
    private int $sequence = 0;

    private static function assertName(string $name): void
    {
        if (!preg_match('/^[a-z][a-z0-9_.:-]{0,119}$/D', $name)) {
            throw new InvalidArgumentException('Invalid module hook name.');
        }
    }

    private function add(array &$registry, string $owner, string $name, callable $callback, int $priority, string $kind): void
    {
        self::assertName($name);
        ModuleHookContracts::assertKind($name, $kind);
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/D', $owner) || $priority < -1000 || $priority > 1000) {
            throw new InvalidArgumentException('Invalid hook owner or priority.');
        }
        $registry[$name][] = ['owner' => $owner, 'callback' => $callback, 'priority' => $priority, 'order' => $this->sequence++];
    }

    public function addAction(string $owner, string $name, callable $callback, int $priority = 10): void
    {
        $this->add($this->actions, $owner, $name, $callback, $priority, 'action');
    }

    public function addFilter(string $owner, string $name, callable $callback, int $priority = 10): void
    {
        $this->add($this->filters, $owner, $name, $callback, $priority, 'filter');
    }

    public function forget(string $owner): void
    {
        foreach (['actions', 'filters'] as $property) {
            foreach ($this->{$property} as $name => $listeners) {
                $this->{$property}[$name] = array_values(array_filter($listeners, static fn(array $row): bool => $row['owner'] !== $owner));
                if (!$this->{$property}[$name]) unset($this->{$property}[$name]);
            }
        }
    }

    private function listeners(array $registry, string $name): array
    {
        self::assertName($name);
        $listeners = $registry[$name] ?? [];
        usort($listeners, static fn(array $a, array $b): int => ($a['priority'] <=> $b['priority']) ?: ($a['order'] <=> $b['order']));
        return $listeners;
    }

    public function doAction(string $name, mixed ...$args): void
    {
        ModuleHookContracts::assertDispatch($name, 'action', $args);
        foreach ($this->listeners($this->actions, $name) as $listener) {
            try {
                ($listener['callback'])(...$args);
            } catch (Throwable $error) {
                error_log('Module action failed [' . $listener['owner'] . ':' . $name . ']: ' . $error->getMessage());
            }
        }
    }

    public function applyFilters(string $name, mixed $value, mixed ...$args): mixed
    {
        ModuleHookContracts::assertKind($name, 'filter');
        foreach ($this->listeners($this->filters, $name) as $listener) {
            try {
                $value = ($listener['callback'])($value, ...$args);
            } catch (Throwable $error) {
                error_log('Module filter failed [' . $listener['owner'] . ':' . $name . ']: ' . $error->getMessage());
            }
        }
        return $value;
    }
}
